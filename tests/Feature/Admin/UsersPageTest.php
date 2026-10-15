<?php

namespace Tests\Feature\Admin;

use App\Models\CarListing;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class UsersPageTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_the_page_renders_the_spanish_kicker_title_and_search_form(): void
    {
        $html = $this->page()->assertViewIs('pages.admin.users.index')->getContent();

        $this->assertMatchesRegularExpression('/lang="es"[^>]*>\s*Registro de usuarios/', $html);
        $this->assertStringContainsString('<h1 class="text-[38px] leading-none md:text-[56px]">App users</h1>', $html);
        $this->assertMatchesRegularExpression('/<form method="GET" action="'.preg_quote(route('admin.users'), '/').'" role="search"/', $html);
        $this->assertMatchesRegularExpression('/<label for="search"[^>]*>Username or email<\/label>\s*<input id="search" name="search" type="search"\s*value=""\s*placeholder="Enter user"[^>]*maxlength="100"/', $html);
    }

    public function test_only_app_users_are_listed_newest_first_then_by_id(): void
    {
        $this->travelTo('2026-10-15 10:00:00');
        $older = User::factory()->create(['created_at' => now()->subDay()]);
        $tiedFirst = User::factory()->create();
        $tiedSecond = User::factory()->create();
        User::factory()->admin()->create(['created_at' => now()->addDay()]);
        $suspended = User::factory()->suspended()->create(['created_at' => now()->subDays(2)]);

        $response = $this->page();

        $this->assertSame([$tiedSecond->id, $tiedFirst->id, $older->id, $suspended->id], $response->viewData('users')->pluck('id')->all());
    }

    public function test_twelve_users_fit_a_page_and_the_thirteenth_moves_to_page_two(): void
    {
        User::factory()->count(13)->sequence(fn ($sequence) => ['created_at' => now()->subMinutes($sequence->index)])->create();

        $this->assertCount(12, $this->page()->viewData('users'));
        $this->assertCount(1, $this->page(['page' => 2])->viewData('users'));
    }

    public function test_a_row_shows_the_columns_and_counts_listings_in_every_status(): void
    {
        $this->travelTo('2026-10-15 10:00:00');
        $user = User::factory()->create(['username' => 'jdoe', 'email' => 'jdoe@example.com', 'phone_number' => '+5355512345']);
        CarListing::factory()->approved()->for($user, 'owner')->create();
        CarListing::factory()->for($user, 'owner')->create();
        CarListing::factory()->rejected()->for($user, 'owner')->create();

        $response = $this->page();

        $this->assertSame(3, $response->viewData('users')->sole()->listings_count);
        $response->assertSeeInOrder(['ID', 'Username', 'Email', 'Phone Number', 'Account Created', 'Status', 'Listings', '<span class="sr-only">Actions</span>'], false)
            ->assertSeeInOrder([(string) $user->id, 'jdoe', 'jdoe@example.com', '+5355512345', '15.10.2026', 'Active', '3'], false);
    }

    public function test_search_matches_username_or_email_case_insensitively_and_never_an_admin(): void
    {
        $byUsername = User::factory()->create(['username' => 'Classic Carla', 'email' => 'carla@example.com']);
        $byEmail = User::factory()->create(['username' => 'ernesto', 'email' => 'classic.ernesto@example.com']);
        User::factory()->create(['username' => 'pedro', 'email' => 'pedro@example.com']);
        User::factory()->admin()->create(['username' => 'classic admin', 'email' => 'boss@example.com']);
        User::factory()->admin()->create(['username' => 'second boss', 'email' => 'classic.boss@example.com']);

        $ids = $this->page(['search' => '  CLASSIC  '])->viewData('users')->pluck('id')->sort()->values()->all();

        $this->assertSame([$byUsername->id, $byEmail->id], $ids);
    }

    public function test_like_wildcards_in_the_term_match_literally(): void
    {
        $underscore = User::factory()->create(['username' => 'fast_car']);
        User::factory()->create(['username' => 'fastXcar']);
        $percent = User::factory()->create(['username' => 'half%off']);
        User::factory()->create(['username' => 'halfoff']);

        $this->assertSame([$underscore->id], $this->page(['search' => 't_c'])->viewData('users')->pluck('id')->all());
        $this->assertSame([$percent->id], $this->page(['search' => 'f%o'])->viewData('users')->pluck('id')->all());
    }

    public function test_the_term_stays_in_the_input_and_the_pagination_links(): void
    {
        User::factory()->count(13)->sequence(fn ($sequence) => ['username' => "seller{$sequence->index}"])->create();

        $html = $this->page(['search' => 'seller'])->getContent();

        $this->assertMatchesRegularExpression('/<input id="search" name="search" type="search"\s*value="seller"/', $html);
        $this->assertStringContainsString(e(route('admin.users', ['search' => 'seller', 'page' => 2])), $html);
    }

    public function test_an_invalid_term_returns_to_the_bare_table_with_the_inline_error(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.users', ['search' => str_repeat('a', 101)]))
            ->assertRedirect(route('admin.users'))
            ->assertSessionHasErrors(['search' => 'The search field must not be greater than 100 characters.']);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.users', ['search' => ['a']]))
            ->assertRedirect(route('admin.users'))
            ->assertSessionHasErrors('search');
    }

    public function test_the_empty_state_shows_for_no_users_no_match_and_a_page_past_the_last(): void
    {
        $this->page()->assertSee('No app users!');

        User::factory()->count(13)->create();

        $this->page(['search' => 'nobody-matches-this'])->assertSee('No app users!');
        $this->page(['page' => 5])
            ->assertSee('No app users!')
            ->assertSee(e(route('admin.users', ['page' => 1])), false);
    }

    public function test_the_status_cell_stamps_a_suspension_and_prints_active_as_plain_text(): void
    {
        $this->travelTo('2026-10-15 10:00:00');
        User::factory()->suspended(now()->addDays(7))->create(['created_at' => now()->subMinute()]);
        User::factory()->suspended()->create(['created_at' => now()->subMinutes(2)]);
        User::factory()->create(['created_at' => now()->subMinutes(3)]);

        $html = $this->page()->getContent();

        $this->assertMatchesRegularExpression('/border-status-rejected text-status-rejected[^>]*>\s*<span[^>]*>\s*<span class="block px-2 py-0.5 text-\[11px\] tracking-\[0.16em\] uppercase">Suspended until 22.10.2026<\/span>/', $html);
        $this->assertMatchesRegularExpression('/<span class="block px-2 py-0.5 text-\[11px\] tracking-\[0.16em\] uppercase">Suspended indefinitely<\/span>/', $html);
        $this->assertStringContainsString('<span class="text-ink-2">Active</span>', $html);
    }

    public function test_each_row_offers_exactly_one_of_suspend_and_unsuspend_with_unique_ids_and_names(): void
    {
        $active = User::factory()->create(['username' => 'carla']);
        $suspended = User::factory()->suspended()->create(['username' => 'pedro']);

        $html = $this->page()->getContent();

        $this->assertSame(1, substr_count($html, 'id="suspend-user-'.$active->id.'"'));
        $this->assertSame(1, substr_count($html, 'id="suspend-user-'.$active->id.'-reason"'));
        $this->assertSame(1, substr_count($html, 'id="suspend-user-'.$active->id.'-duration"'));
        $this->assertSame(1, substr_count($html, 'id="delete-user-'.$active->id.'"'));
        $this->assertSame(1, substr_count($html, 'id="delete-user-'.$suspended->id.'"'));
        $this->assertStringNotContainsString('suspend-user-'.$suspended->id, $html);
        $this->assertStringNotContainsString(route('admin.users.unsuspend', $active), $html);
        $this->assertStringContainsString('action="'.route('admin.users.unsuspend', $suspended).'"', $html);
        foreach (['View listings of carla', 'Suspend carla', 'Delete carla', 'View listings of pedro', 'Unsuspend pedro', 'Delete pedro'] as $name) {
            $this->assertStringContainsString('<span class="sr-only">'.$name.'</span>', $html);
        }
        $this->assertStringContainsString('href="'.route('users.listings', $active).'" class="inline-flex size-11 items-center justify-center rounded-xs text-ink hover:bg-red/10 active:bg-red/18 lg:size-9"', $html);
        $this->assertSame(2 * 7, substr_count($html, 'text-[11px] tracking-[0.12em] text-muted uppercase lg:hidden'));
    }

    public function test_the_suspend_dialog_holds_the_reason_and_the_duration_options(): void
    {
        $user = User::factory()->create(['username' => 'carla']);

        $html = $this->page()->getContent();

        $this->assertStringContainsString('<h2 id="suspend-user-'.$user->id.'-title" class="text-[26px] leading-tight text-red italic">Suspend carla?</h2>', $html);
        $this->assertMatchesRegularExpression('/<textarea id="suspend-user-'.$user->id.'-reason" name="reason"\s*required aria-required="true"\s*aria-describedby="suspend-user-'.$user->id.'-reason-hint"[^>]*maxlength="500"/', $html);
        $this->assertStringContainsString('<p id="suspend-user-'.$user->id.'-reason-hint" class="text-xs text-muted">Shown to the user when they try to sign in. Max 500 characters.</p>', $html);
        $this->assertMatchesRegularExpression('/<select id="suspend-user-'.$user->id.'-duration" name="duration"\s*required[^>]*>\s*<option value="">Choose a duration<\/option>\s*<option value="7_days"\s*>7 days<\/option>\s*<option value="30_days"\s*>30 days<\/option>\s*<option value="indefinite"\s*>Indefinite<\/option>/', $html);
        $this->assertStringContainsString('<h2 id="delete-user-'.$user->id.'-title" class="text-[26px] leading-tight text-red italic">Are you sure you want to delete carla?</h2>', $html);
        $this->assertMatchesRegularExpression('/This can&#039;t be undone\..*Delete User Account/s', $html);
    }

    public function test_action_urls_carry_the_current_search_and_page(): void
    {
        User::factory()->count(12)->sequence(fn ($sequence) => ['username' => "seller{$sequence->index}", 'created_at' => now()->subMinutes($sequence->index)])->create();
        $last = User::factory()->create(['username' => 'seller-last', 'created_at' => now()->subDay()]);

        $html = $this->page(['search' => 'seller', 'page' => 2])->getContent();

        $this->assertStringContainsString('action="'.e(route('admin.users.suspend', [$last, 'search' => 'seller', 'page' => 2])).'"', $html);
        $this->assertStringContainsString('action="'.e(route('admin.users.destroy', [$last, 'search' => 'seller', 'page' => 2])).'"', $html);
    }

    public function test_the_admin_nav_lists_users_before_conversations_without_a_count(): void
    {
        $html = $this->page()->getContent();

        $this->assertMatchesRegularExpression('/<a href="'.preg_quote(route('admin.users'), '/').'"\s+aria-current="page"\s+class="[^"]*">\s*Users\s*<\/a>/', $html);
        $this->assertSeeInOrderHtml($html, ['href="'.route('admin.users').'"', 'href="'.route('conversations.index').'"']);
    }

    public function test_the_query_count_does_not_grow_with_the_rows(): void
    {
        $admin = User::factory()->admin()->create();
        $this->usersWithListings(2);
        $few = $this->queriesFor($admin);

        $this->usersWithListings(10);
        $many = $this->queriesFor($admin);

        $this->assertCount(12, $this->page([], $admin)->viewData('users'));
        $this->assertSame($few, $many);
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function page(array $query = [], ?User $admin = null): TestResponse
    {
        $response = $this->actingAs($admin ?? User::factory()->admin()->create())->get(route('admin.users', $query));
        $this->app['auth']->forgetGuards();

        return $response->assertOk();
    }

    private function usersWithListings(int $count): void
    {
        User::factory()->count($count)->has(CarListing::factory()->count(2), 'listings')->create();
    }

    private function queriesFor(User $admin): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->page([], $admin);
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    /**
     * @param  list<string>  $needles
     */
    private function assertSeeInOrderHtml(string $html, array $needles): void
    {
        $position = 0;

        foreach ($needles as $needle) {
            $found = strpos($html, $needle, $position);
            $this->assertNotFalse($found, "Missing or out of order: {$needle}");
            $position = $found + strlen($needle);
        }
    }
}
