<?php

namespace Tests\Feature\Conversations;

use App\Enums\ListingStatus;
use App\Enums\UserStatus;
use App\Models\CarListing;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ConversationsListPageTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const string UNREAD_VARIANTS = 'data-unread:border-l-3 data-unread:border-l-red data-unread:bg-card-unread data-unread:shadow-md"';

    private const string UNREAD_ATTRIBUTE = '/^<article\b[^>]*\sdata-unread(?=[\s>])/';

    private const string STAMP_WRAPPER = '<div class="pointer-events-none absolute -top-2.5 -right-2.5 hidden rotate-4 group-data-unread:block motion-public:animate-stamp-in">';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Storage::fake('public');
    }

    public function test_a_guest_is_sent_to_login(): void
    {
        $this->get(route('conversations.index'))->assertRedirect(route('login'));
    }

    public function test_an_active_app_user_and_an_admin_reach_the_page(): void
    {
        $this->list(User::factory()->create())->assertSee('My Conversations');
        $this->list(User::factory()->admin()->create())->assertSee('All conversations');
    }

    public function test_a_participant_sees_their_conversations_on_both_sides_and_nobody_elses(): void
    {
        $viewer = User::factory()->create();
        $buying = Conversation::factory()->for($viewer, 'sender')->for(CarListing::factory()->approved()->state(['name' => 'Buying Bel Air']), 'listing')->create();
        $selling = Conversation::factory()->for(CarListing::factory()->approved()->for($viewer, 'owner')->state(['name' => 'Selling Impala']), 'listing')->create();
        Conversation::factory()->for(CarListing::factory()->approved()->state(['name' => 'Stranger Thunderbird']), 'listing')->create();

        $response = $this->list($viewer);

        $this->assertCount(2, $this->cards($response));
        $response->assertSee($buying->listing->name)
            ->assertSee($selling->listing->name)
            ->assertDontSee('Stranger Thunderbird');
    }

    public function test_an_admin_sees_every_conversation_headed_by_buyer_and_seller(): void
    {
        $first = $this->conversationBetween('cuban buyer', 'havana seller');
        $second = $this->conversationBetween('miami buyer', 'trinidad seller');

        $response = $this->list(User::factory()->admin()->create());

        $this->assertCount(2, $this->cards($response));
        $response->assertSee('cuban buyer · havana seller')
            ->assertSee('miami buyer · trinidad seller')
            ->assertSee(route('conversations.show', $first))
            ->assertSee(route('conversations.show', $second));
    }

    public function test_each_participant_sees_the_other_party_as_the_heading(): void
    {
        $conversation = $this->conversationBetween('cuban buyer', 'havana seller');

        $this->list($conversation->sender)->assertSeeInOrder(['<h2', 'havana seller', '</h2>'], false)->assertDontSee('cuban buyer');
        $this->list($conversation->receiver)->assertSeeInOrder(['<h2', 'cuban buyer', '</h2>'], false)->assertDontSee('havana seller');
    }

    public function test_the_card_links_to_the_thread_with_the_listing_as_context(): void
    {
        $conversation = $this->conversationBetween('cuban buyer', 'havana seller', 'Bel Air Sport');

        $this->list($conversation->sender)->assertSeeInOrder([
            '<a href="'.route('conversations.show', $conversation).'" class="after:absolute after:inset-0 focus-visible:outline-none">havana seller<span class="sr-only"> regarding Bel Air Sport</span></a>',
            'Regarding: Bel Air Sport',
        ], false);
    }

    public function test_cards_are_ordered_by_last_activity_with_nulls_last_and_ties_by_newest_id(): void
    {
        $this->travelTo('2026-10-15 12:00:00');
        $seller = User::factory()->create();
        $listing = CarListing::factory()->approved()->for($seller, 'owner')->create();
        $this->buyerOn($listing, 'newest buyer', '2026-10-15 11:00:00');
        $this->buyerOn($listing, 'older tie buyer', '2026-10-15 09:00:00');
        $this->buyerOn($listing, 'silent buyer', null);
        $this->buyerOn($listing, 'newer tie buyer', '2026-10-15 09:00:00');

        $this->list($seller)->assertSeeInOrder(['newest buyer', 'newer tie buyer', 'older tie buyer', 'silent buyer']);
    }

    public function test_eighteen_cards_fit_a_page_and_the_nineteenth_moves_to_page_two(): void
    {
        $seller = User::factory()->create();
        $listing = CarListing::factory()->approved()->for($seller, 'owner')->create();
        foreach (range(1, 19) as $minutesAgo) {
            $this->buyerOn($listing, "buyer {$minutesAgo} min", now()->subMinutes($minutesAgo)->toDateTimeString());
        }

        $first = $this->list($seller);
        $second = $this->list($seller, ['page' => 2]);

        $this->assertCount(18, $this->cards($first));
        $first->assertDontSee('buyer 19 min');
        $this->assertCount(1, $this->cards($second));
        $second->assertSee('buyer 19 min');
    }

    public function test_a_page_past_the_last_keeps_the_header_and_links_without_cards_or_empty_state(): void
    {
        $conversation = Conversation::factory()->create();
        $this->message($conversation, $conversation->receiver, 'Are you still selling?');

        $response = $this->list($conversation->sender, ['page' => 5]);

        $this->assertCount(0, $this->cards($response));
        $response->assertSee('My Conversations')
            ->assertSee('1 unread')
            ->assertSee(__('app.pagination.label'))
            ->assertDontSee('You have no active conversations');
    }

    public function test_the_card_shows_the_newest_message_escaped_and_its_time(): void
    {
        $this->travelTo('2026-10-15 12:00:00');
        $conversation = Conversation::factory()->create();
        $this->message($conversation, $conversation->sender, 'Hello, is it still for sale?', '2026-10-15 08:00:00');
        $this->message($conversation, $conversation->receiver, 'Is the <b>engine</b> original?', '2026-10-15 10:00:00');

        $card = $this->cards($this->list($conversation->sender))[0];

        $this->assertStringContainsString('Is the &lt;b&gt;engine&lt;/b&gt; original?', $card);
        $this->assertStringNotContainsString('Hello, is it still for sale?', $card);
        $this->assertStringContainsString('<time datetime="2026-10-15T10:00:00+00:00" data-card-time="" class="shrink-0 text-[11px] text-muted">2 hours ago</time>', $card);
    }

    public function test_an_emptied_conversation_reads_no_messages_with_its_last_activity_time(): void
    {
        $this->travelTo('2026-10-15 12:00:00');
        $conversation = Conversation::factory()->create(['last_message_at' => '2026-10-12 12:00:00']);

        $card = $this->cards($this->list($conversation->sender))[0];

        $this->assertStringContainsString('data-empty:text-muted data-empty:italic" data-card-message="" data-empty>No messages</p>', $card);
        $this->assertStringContainsString('data-last-message-id="0"', $card);
        $this->assertStringContainsString('<time datetime="2026-10-12T12:00:00+00:00"', $card);
        $this->assertStringContainsString('3 days ago', $card);
    }

    public function test_an_emptied_conversation_without_a_last_activity_shows_no_time(): void
    {
        $conversation = Conversation::factory()->create(['last_message_at' => null]);

        $card = $this->cards($this->list($conversation->sender))[0];

        $this->assertStringContainsString('No messages', $card);
        $this->assertStringContainsString('<time data-card-time hidden class="shrink-0 text-[11px] text-muted"></time>', $card);
        $this->assertStringNotContainsString('datetime=', $card);
    }

    public function test_the_card_photo_is_the_listings_thumb(): void
    {
        $conversation = Conversation::factory()->for(CarListing::factory()->approved()->withImages(2), 'listing')->create();
        $thumb = $conversation->listing->images()->where('position', 0)->sole()->thumbUrl();

        $card = $this->cards($this->list($conversation->sender))[0];

        $this->assertStringContainsString('<img src="'.$thumb.'" alt="" width="320" height="240" loading="lazy" class="aspect-4/3 w-24 self-start object-cover photo-grade">', $card);
        $this->assertStringNotContainsString('This listing is no longer available.', $card);
    }

    public function test_a_listing_without_photos_shows_the_placeholder_frame(): void
    {
        $conversation = Conversation::factory()->create();

        $card = $this->cards($this->list($conversation->sender))[0];

        $this->assertStringNotContainsString('<img', $card);
        $this->assertStringContainsString('border border-rule bg-paper text-muted', $card);
    }

    #[DataProvider('hiddenListings')]
    public function test_the_buyer_sees_only_the_name_of_a_listing_that_left_public_view(string $state): void
    {
        $conversation = Conversation::factory()->for(CarListing::factory()->approved()->withImages()->state(['name' => 'Hidden Bel Air']), 'listing')->create();
        $this->hide($conversation, $state);
        $admin = User::factory()->admin()->create();

        $buyerCard = $this->cards($this->list($conversation->sender))[0];

        $this->assertStringNotContainsString('<img', $buyerCard);
        $this->assertStringContainsString('Regarding: Hidden Bel Air', $buyerCard);
        $this->assertStringContainsString('This listing is no longer available.', $buyerCard);
        foreach ($state === 'suspended owner' ? [$admin] : [$conversation->receiver, $admin] as $viewer) {
            $card = $this->cards($this->list($viewer))[0];
            $this->assertStringContainsString('<img', $card);
            $this->assertStringNotContainsString('This listing is no longer available.', $card);
        }
    }

    /**
     * @return array<string, array{string}>
     */
    public static function hiddenListings(): array
    {
        return [
            'pending' => ['pending'],
            'rejected' => ['rejected'],
            'suspended owner' => ['suspended owner'],
        ];
    }

    public function test_a_card_with_the_other_partys_unread_messages_is_styled_and_stamped_with_its_count(): void
    {
        $conversation = Conversation::factory()->create();
        $this->message($conversation, $conversation->receiver, 'First unread question.');
        $latest = $this->message($conversation, $conversation->receiver, 'Second unread question.');

        $card = $this->cards($this->list($conversation->sender))[0];

        $unreadId = "conversation-{$conversation->id}-unread";
        $this->assertStringContainsString(self::UNREAD_VARIANTS, $card);
        $this->assertStringContainsString('data-conversation-card="'.$conversation->id.'" data-unread-count="2" data-last-message-id="'.$latest->id.'" data-unread>', $card);
        $this->assertMatchesRegularExpression(self::UNREAD_ATTRIBUTE, $card);
        $this->assertStringContainsString(self::STAMP_WRAPPER, $card);
        $this->assertStringContainsString('<div class="flex items-start justify-between gap-3 group-data-unread:pr-12">', $card);
        $this->assertStringContainsString('<span class="uppercase">New</span><span id="'.$unreadId.'" data-card-count data-template-one=":count unread message" data-template-other=":count unread messages" class="sr-only"> 2 unread messages</span>', $card);
        $this->assertStringContainsString('<a href="'.route('conversations.show', $conversation).'" aria-describedby="'.$unreadId.'"', $card);
    }

    public function test_the_whole_card_shows_the_focus_ring_of_its_link(): void
    {
        $conversation = Conversation::factory()->create();

        $card = $this->cards($this->list($conversation->sender))[0];

        $this->assertStringContainsString('has-[a:focus-visible]:outline-2 has-[a:focus-visible]:outline-offset-2 has-[a:focus-visible]:outline-gold-ink', $card);
        $this->assertStringContainsString('focus-visible:outline-none', $card);
    }

    public function test_the_stamp_names_a_single_unread_message_in_the_singular(): void
    {
        $conversation = Conversation::factory()->create();
        $this->message($conversation, $conversation->receiver, 'Only unread question.');

        $this->assertStringContainsString('class="sr-only"> 1 unread message</span>', $this->cards($this->list($conversation->sender))[0]);
    }

    public function test_the_viewers_own_unread_and_the_other_partys_read_messages_leave_the_card_read(): void
    {
        $conversation = Conversation::factory()->create();
        $this->message($conversation, $conversation->sender, 'My own unread message.');
        $this->message($conversation, $conversation->receiver, 'An answer I already read.', readAt: now()->toDateTimeString());

        $response = $this->list($conversation->sender);
        $card = $this->cards($response)[0];

        $this->assertStringContainsString(self::UNREAD_VARIANTS, $card);
        $this->assertStringContainsString('data-unread-count="0" data-last-message-id="', $card);
        $this->assertDoesNotMatchRegularExpression(self::UNREAD_ATTRIBUTE, $card);
        $this->assertStringContainsString(self::STAMP_WRAPPER, $card);
        $this->assertStringNotContainsString('sr-only"> 1 unread', $card);
        $this->assertStringNotContainsString('aria-describedby', $card);
        $response->assertDontSee('1 unread');
    }

    public function test_an_admin_never_sees_unread_state(): void
    {
        $conversation = Conversation::factory()->create();
        $this->message($conversation, $conversation->sender, 'Unread by the seller.');
        $this->message($conversation, $conversation->receiver, 'Unread by the buyer.');

        $response = $this->list(User::factory()->admin()->create());
        $card = $this->cards($response)[0];

        $this->assertStringContainsString(self::UNREAD_VARIANTS, $card);
        $this->assertDoesNotMatchRegularExpression(self::UNREAD_ATTRIBUTE, $card);
        $this->assertStringNotContainsString('group-data-unread:block', $card);
        $this->assertStringNotContainsString('data-conversation-card', $card);
        $this->assertStringNotContainsString('data-card-', $card);
        $this->assertStringNotContainsString('unread message', $card);
        $response->assertDontSee('1 unread')->assertViewHas('unreadCount', 0)
            ->assertDontSee('data-live-', false);
    }

    public function test_the_unread_line_counts_messages_on_every_page(): void
    {
        $seller = User::factory()->create();
        $listing = CarListing::factory()->approved()->for($seller, 'owner')->create();
        foreach (range(1, 19) as $minutesAgo) {
            $conversation = $this->buyerOn($listing, "buyer {$minutesAgo} min", now()->subMinutes($minutesAgo)->toDateTimeString());
            $this->message($conversation, $conversation->sender, 'A question from the buyer.');
            if ($minutesAgo === 19) {
                $this->message($conversation, $conversation->sender, 'A second question on page two.');
            }
        }

        $this->list($seller)
            ->assertViewHas('unreadCount', 20)
            ->assertSeeInOrder(['My Conversations', '<p role="status" data-live-unread data-template=":count unread" class="text-sm text-ink-2 tabular-nums">20 unread</p>'], false);
    }

    public function test_the_unread_line_is_an_empty_status_at_zero(): void
    {
        $conversation = Conversation::factory()->create();
        $this->message($conversation, $conversation->sender, 'Nobody has answered yet.');

        $this->list($conversation->sender)
            ->assertSee('<p role="status" data-live-unread data-template=":count unread" class="text-sm text-ink-2 tabular-nums"></p>', false);
    }

    public function test_a_participants_list_is_live_with_the_notice_region_and_its_template(): void
    {
        $conversation = Conversation::factory()->create();

        $response = $this->list($conversation->sender);

        $response->assertSee('data-live-list="" data-first-page>', false)
            ->assertSeeInOrder([
                '<div role="status" data-live-notice-region></div>',
                '<template data-live-notice>',
                '<p class="mt-4 border-l-3 border-l-red bg-card-unread px-4 py-3 text-sm text-ink md:mt-6">New messages — <a href="'.route('conversations.index').'" class="text-red underline">refresh</a></p>',
                '<article',
            ], false);
    }

    public function test_a_later_page_is_live_without_the_first_page_marker(): void
    {
        $seller = User::factory()->create();
        $listing = CarListing::factory()->approved()->for($seller, 'owner')->create();
        foreach (range(1, 19) as $minutesAgo) {
            $this->buyerOn($listing, "buyer {$minutesAgo} min", now()->subMinutes($minutesAgo)->toDateTimeString());
        }

        $this->list($seller, ['page' => 2])
            ->assertSee('data-live-list="">', false)
            ->assertDontSee('data-first-page', false);
    }

    public function test_an_empty_participant_list_stays_live_with_its_notice_region(): void
    {
        $this->list(User::factory()->create())
            ->assertSee('data-live-list="" data-first-page>', false)
            ->assertSeeInOrder(['<div role="status" data-live-notice-region></div>', 'You have no active conversations'], false)
            ->assertSee('data-live-unread', false);
    }

    public function test_an_admins_list_is_never_live(): void
    {
        Conversation::factory()->create();

        $this->list(User::factory()->admin()->create())
            ->assertDontSee('data-live-', false)
            ->assertDontSee('<template data-live-notice>', false);
    }

    public function test_an_admins_list_has_no_unread_status_line(): void
    {
        $conversation = Conversation::factory()->create();
        $this->message($conversation, $conversation->sender, 'Unread by the seller.');

        $this->list(User::factory()->admin()->create())
            ->assertDontSee('<p role="status"', false);
    }

    public function test_a_suspended_participant_in_a_live_session_is_signed_out_of_the_list(): void
    {
        $suspended = User::factory()->suspended()->create();
        $conversation = Conversation::factory()->for($suspended, 'sender')->create();
        $this->message($conversation, $conversation->receiver, 'Still interested in it?');

        $this->actingAs($suspended)->get(route('conversations.index'))->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_a_member_without_conversations_is_invited_to_browse_listings(): void
    {
        $response = $this->list(User::factory()->create());

        $this->assertCount(0, $this->cards($response));
        $response->assertSeeInOrder(['You have no active conversations', 'href="'.route('listings.index').'"', 'Browse listings'], false);
    }

    public function test_an_admin_without_conversations_sees_the_admin_empty_state_without_an_action(): void
    {
        $response = $this->list(User::factory()->admin()->create());

        $response->assertSee('No conversations yet.')
            ->assertDontSee('You have no active conversations')
            ->assertDontSee('Browse listings');
    }

    public function test_listing_conversations_writes_nothing(): void
    {
        $this->travelTo('2026-10-15 10:00:00');
        $conversation = Conversation::factory()->create();
        $unread = $this->message($conversation, $conversation->receiver, 'Still unread after the visit.');
        $this->travelTo('2026-10-15 12:00:00');

        $this->list($conversation->sender);
        $this->list(User::factory()->admin()->create());

        $this->assertNull($unread->fresh()->read_at);
        $this->assertSame('2026-10-15 10:00:00', $conversation->fresh()->last_message_at->toDateTimeString());
    }

    public function test_the_conversations_nav_item_is_current_on_the_list_the_thread_and_a_new_conversation(): void
    {
        $conversation = Conversation::factory()->create();
        $this->message($conversation, $conversation->receiver, 'Unread for the nav check.');
        $current = '/<a href="'.preg_quote(route('conversations.index'), '/').'"\s+aria-current="page"/';

        $list = $this->list($conversation->sender)->getContent();
        $thread = $this->actingAs($conversation->sender)->get(route('conversations.show', $conversation))->assertOk()->getContent();
        $new = $this->actingAs($conversation->sender)->get(route('conversations.create', CarListing::factory()->approved()->create()))->assertOk()->getContent();

        foreach ([$list, $thread, $new] as $page) {
            $this->assertMatchesRegularExpression($current, $page);
        }
        $this->assertStringContainsString('data-nav-count', $list);
        $this->assertStringNotContainsString('data-nav-count', $thread);
        $this->assertStringNotContainsString('data-menu-signal', $thread);
    }

    public function test_many_cards_load_without_lazy_loading_and_a_constant_query_count(): void
    {
        $viewer = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $this->conversationsOf($viewer, 1);
        $participantOne = $this->queriesFor($viewer);
        $adminOne = $this->queriesFor($admin);

        $this->conversationsOf($viewer, 17);
        $participantMany = $this->queriesFor($viewer);
        $adminMany = $this->queriesFor($admin);

        $this->assertSame($participantOne, $participantMany);
        $this->assertSame($adminOne, $adminMany);
    }

    private function conversationsOf(User $viewer, int $count): void
    {
        foreach (range(1, $count) as $index) {
            $conversation = Conversation::factory()->for($viewer, $index % 2 === 0 ? 'sender' : 'receiver')
                ->for(CarListing::factory()->approved()->withImages()->state(fn (): array => $index % 2 === 0 ? [] : ['user_id' => $viewer->id]), 'listing')
                ->create();
            $this->message($conversation, $conversation->otherParticipant($viewer), 'A message for the card.');
        }
    }

    private function queriesFor(User $viewer): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->list($viewer);
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function list(User $viewer, array $query = []): TestResponse
    {
        $response = $this->actingAs($viewer)->get(route('conversations.index', $query));
        $this->app['auth']->forgetGuards();

        return $response->assertOk();
    }

    /**
     * @return list<string>
     */
    private function cards(TestResponse $response): array
    {
        preg_match_all('/<article\b.*?<\/article>/s', $response->getContent(), $matches);

        return $matches[0];
    }

    private function conversationBetween(string $buyer, string $seller, string $listingName = 'Bel Air'): Conversation
    {
        $listing = CarListing::factory()->approved()->for(User::factory()->create(['username' => $seller]), 'owner')->create(['name' => $listingName]);

        return Conversation::factory()->for($listing, 'listing')->for(User::factory()->create(['username' => $buyer]), 'sender')->create();
    }

    private function buyerOn(CarListing $listing, string $buyer, ?string $lastMessageAt): Conversation
    {
        return Conversation::factory()->for($listing, 'listing')->for(User::factory()->create(['username' => $buyer]), 'sender')
            ->create(['last_message_at' => $lastMessageAt]);
    }

    private function message(Conversation $conversation, User $sender, string $text, ?string $sentAt = null, ?string $readAt = null): Message
    {
        $sentAt ??= now()->toDateTimeString();

        return Message::factory()->for($conversation)->create([
            'sender_id' => $sender->id,
            'message' => $text,
            'read_at' => $readAt,
            'created_at' => $sentAt,
            'updated_at' => $sentAt,
        ]);
    }

    private function hide(Conversation $conversation, string $state): void
    {
        match ($state) {
            'pending' => $conversation->listing->forceFill(['status' => ListingStatus::Pending])->save(),
            'rejected' => $conversation->listing->forceFill(['status' => ListingStatus::Rejected, 'rejection_reason' => 'Blurry photos.'])->save(),
            'suspended owner' => $conversation->receiver->forceFill(['status' => UserStatus::Suspended])->save(),
        };
    }
}
