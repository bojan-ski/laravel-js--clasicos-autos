<?php

namespace Tests\Feature\Admin;

use App\Models\CarListing;
use App\Models\User;
use App\Support\PendingListingsCounter;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PendingListingsCounterTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_it_counts_every_pending_listing_including_suspended_owners(): void
    {
        CarListing::factory()->count(2)->create();
        CarListing::factory()->for(User::factory()->suspended(), 'owner')->create();
        CarListing::factory()->customModel()->create();
        CarListing::factory()->approved()->create();
        CarListing::factory()->rejected()->create();

        $this->assertSame(4, app(PendingListingsCounter::class)->count(User::factory()->admin()->create()));
    }

    public function test_a_guest_or_an_app_user_gets_zero_without_a_query(): void
    {
        CarListing::factory()->create();
        $appUser = User::factory()->create();
        $counter = app(PendingListingsCounter::class);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $counts = [$counter->count(null), $counter->count($appUser)];
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertSame([0, 0], $counts);
        $this->assertSame([], $queries);
    }

    public function test_every_admin_page_shows_the_badge_and_none_at_zero(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('admin.account'))->assertOk()->assertDontSee('data-nav-count', false);

        CarListing::factory()->count(3)->create();

        foreach (['admin.account', 'admin.users', 'admin.listings', 'conversations.index'] as $route) {
            $html = $this->actingAs($admin)->get(route($route))->assertOk()->getContent();

            $this->assertMatchesRegularExpression('/href="'.preg_quote(route('admin.listings'), '/').'"[^>]*>\s*Listings\s*<span[^>]*data-nav-count[^>]*>\s*<span[^>]*>\s*<span[^>]*>3<span class="sr-only"> pending<\/span><\/span>/', $html, $route);
        }
    }

    public function test_an_app_user_never_sees_the_listings_queue_badge(): void
    {
        CarListing::factory()->count(3)->create();

        $this->actingAs(User::factory()->create())->get(route('profile'))
            ->assertOk()
            ->assertDontSee(route('admin.listings'), false)
            ->assertDontSee(' pending</span>', false);
    }
}
