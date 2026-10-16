<?php

namespace Tests\Feature\Conversations;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Support\UnreadMessagesCounter;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class UnreadNavCountTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const string PILL_CLASSES = 'class="absolute -top-2.5 -right-4 h-4.5 min-w-4.5 rounded-full bg-red px-1.25 text-center text-[11px] leading-4.5 text-cream-on-dark tabular-nums motion-public:data-bump:animate-pop"';

    private const string DOT_CLASSES = 'class="absolute top-2 right-2 size-2.5 rounded-full bg-red motion-public:data-bump:animate-pop"';

    private const string DOT_TEXT = '<span class="sr-only"> (unread messages)</span></span>';

    private const string STAMP_CLASSES = 'class="inline-block border-2 border-dashed p-1 border-red text-red bg-card"';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_a_member_with_unread_messages_sees_the_pill_the_stamp_and_the_dot(): void
    {
        $member = User::factory()->create();
        $this->unreadFor($member, 2);
        $this->unreadFor($member, 1);

        $this->page($member)
            ->assertSee('<span data-unread-badge data-nav-count data-template=":count" '.self::PILL_CLASSES.'><span data-unread-badge-text>3</span><span class="sr-only"> unread</span></span>', false)
            ->assertSee('<span '.self::STAMP_CLASSES.' data-unread-badge="data-unread-badge" data-nav-count="data-nav-count" data-template=":count NEW">', false)
            ->assertSee('3 NEW')
            ->assertSee('<span data-unread-dot data-menu-signal '.self::DOT_CLASSES.'>'.self::DOT_TEXT, false);
    }

    public function test_the_menu_button_is_named_by_its_text_so_the_dot_adds_the_unread_cue(): void
    {
        $member = User::factory()->create();
        $this->unreadFor($member, 1);

        $this->page($member)
            ->assertSeeInOrder(['<summary class=', '<span class="sr-only">Menu</span>', 'data-unread-dot', self::DOT_TEXT, '</summary>'], false)
            ->assertDontSee('<summary aria-label', false);
    }

    public function test_a_live_member_at_zero_gets_hidden_targets_and_no_count_markers(): void
    {
        $member = User::factory()->create();
        $this->unreadFor(User::factory()->create(), 2);

        $this->page($member)
            ->assertSee('<span data-unread-badge hidden data-template=":count" '.self::PILL_CLASSES.'><span data-unread-badge-text>0</span>', false)
            ->assertSee('<span '.self::STAMP_CLASSES.' data-unread-badge="data-unread-badge" hidden="hidden" data-template=":count NEW">', false)
            ->assertSee('<span data-unread-dot hidden '.self::DOT_CLASSES.'>'.self::DOT_TEXT, false)
            ->assertDontSee('data-nav-count', false)
            ->assertDontSee('data-menu-signal', false);
    }

    public function test_a_suspended_member_in_a_live_session_is_signed_out_before_any_header_renders(): void
    {
        $member = User::factory()->suspended()->create();
        $this->unreadFor($member, 2);

        $this->actingAs($member)->get(route('home'))->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_an_admin_never_sees_a_count_even_with_unread_messages_present(): void
    {
        $this->unreadFor(User::factory()->create(), 2);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('conversations.index'))
            ->assertOk()
            ->assertSee('href="'.route('conversations.index').'"', false)
            ->assertDontSee('data-unread-badge', false)
            ->assertDontSee('data-unread-dot', false)
            ->assertDontSee('data-nav-count', false)
            ->assertDontSee('data-live-channel', false);
    }

    public function test_a_guests_header_has_no_conversations_count(): void
    {
        $this->unreadFor(User::factory()->create(), 2);

        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee('data-unread-badge', false)
            ->assertDontSee('data-unread-dot', false)
            ->assertDontSee('data-live-channel', false);
    }

    public function test_only_an_active_app_user_gets_the_live_channel(): void
    {
        $member = User::factory()->create();

        $this->page($member)->assertSee('<header data-header-variant="member" data-live-channel="user.'.$member->id.'"', false);
        $this->actingAs(User::factory()->admin()->create())->get(route('admin.account'))->assertOk()->assertDontSee('data-live-channel', false);
    }

    public function test_on_a_thread_the_count_excludes_the_messages_that_visit_marked(): void
    {
        $member = User::factory()->create();
        $visited = $this->unreadFor($member, 2);
        $this->unreadFor($member, 1);

        $this->actingAs($member)
            ->get(route('conversations.show', $visited))
            ->assertOk()
            ->assertSee('<span data-unread-badge-text>1</span>', false)
            ->assertSee('1 NEW');
    }

    public function test_on_the_list_the_count_equals_the_unread_line(): void
    {
        $member = User::factory()->create();
        $this->unreadFor($member, 2);
        $this->unreadFor($member, 3);

        $this->actingAs($member)
            ->get(route('conversations.index'))
            ->assertOk()
            ->assertSee('<span data-unread-badge-text>5</span>', false)
            ->assertSee('class="text-sm text-ink-2 tabular-nums">5 unread</p>', false);
    }

    public function test_the_counter_queries_messages_for_a_member_page_and_never_for_an_admin_page(): void
    {
        $member = User::factory()->create();
        $admin = User::factory()->admin()->create();

        $this->assertSame(1, $this->messageQueriesOn($member, route('home')));
        $this->assertSame(0, $this->messageQueriesOn($admin, route('admin.account')));
    }

    public function test_the_counter_is_zero_for_a_guest_without_a_query(): void
    {
        $this->unreadFor(User::factory()->create(), 2);
        DB::flushQueryLog();
        DB::enableQueryLog();

        $count = app(UnreadMessagesCounter::class)->count(null);

        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        $this->assertSame(0, $count);
        $this->assertSame([], $queries);
    }

    private function messageQueriesOn(User $viewer, string $url): int
    {
        $this->actingAs($viewer);
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->get($url)->assertOk();

        $queries = array_column(DB::getQueryLog(), 'query');
        DB::disableQueryLog();
        $this->app['auth']->forgetGuards();

        return count(array_filter($queries, fn (string $sql): bool => str_contains($sql, '`messages`')));
    }

    private function page(User $viewer): TestResponse
    {
        $response = $this->actingAs($viewer)->get(route('home'))->assertOk();
        $this->app['auth']->forgetGuards();

        return $response;
    }

    private function unreadFor(User $member, int $count): Conversation
    {
        $conversation = Conversation::factory()->for($member, 'sender')->create();
        Message::factory()->for($conversation)->count($count)->create(['sender_id' => $conversation->receiver_id]);
        Message::factory()->for($conversation)->create(['sender_id' => $member->id]);

        return $conversation;
    }
}
