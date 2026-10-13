<?php

namespace Tests\Feature\Bookmarks;

use App\Enums\UserStatus;
use App\Models\CarListing;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BookmarksPageTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_the_page_has_its_title_and_a_spanish_kicker(): void
    {
        $response = $this->actingAs(User::factory()->create())->get(route('bookmarks.index'));

        $response->assertOk()
            ->assertSee('<title>Bookmarked Car Listings — Clásicos Autos</title>', false)
            ->assertSee('<h1 class="text-[38px] leading-none md:text-[56px]">'.__('app.bookmarks.title').'</h1>', false);
        $this->assertMatchesRegularExpression('/<span[^>]*lang="es"[^>]*>'.preg_quote(__('app.bookmarks.kicker'), '/').'<\/span>/', (string) $response->getContent());
    }

    public function test_listings_are_ordered_by_bookmark_time_not_post_date(): void
    {
        $user = User::factory()->create();
        $postedEarlier = CarListing::factory()->approved()->create(['name' => 'Posted Earlier Bel Air', 'created_at' => now()->subDays(5)]);
        $postedLater = CarListing::factory()->approved()->create(['name' => 'Posted Later Impala', 'created_at' => now()->subDay()]);
        $this->bookmark($user, $postedLater, now()->subDays(3));
        $this->bookmark($user, $postedEarlier, now()->subHour());

        $response = $this->actingAs($user)->get(route('bookmarks.index'));

        $response->assertSeeInOrder([$postedEarlier->name, $postedLater->name]);
    }

    public function test_bookmarks_made_at_the_same_moment_put_the_newer_listing_first(): void
    {
        $user = User::factory()->create();
        $older = CarListing::factory()->approved()->create(['name' => 'Older Row Thunderbird']);
        $newer = CarListing::factory()->approved()->create(['name' => 'Newer Row Fairlane']);
        $this->bookmark($user, $older, now()->subHour());
        $this->bookmark($user, $newer, now()->subHour());

        $response = $this->actingAs($user)->get(route('bookmarks.index'));

        $response->assertSeeInOrder([$newer->name, $older->name]);
    }

    public function test_twelve_listings_fill_a_page_and_the_thirteenth_moves_to_page_two(): void
    {
        $user = User::factory()->create();
        $oldest = CarListing::factory()->approved()->create(['name' => 'Oldest Saved Edsel']);
        $this->bookmark($user, $oldest, now()->subDay());
        foreach (CarListing::factory()->approved()->count(12)->create() as $listing) {
            $this->bookmark($user, $listing, now()->subHour());
        }
        $this->actingAs($user);

        $first = $this->get(route('bookmarks.index'));
        $second = $this->get(route('bookmarks.index', ['page' => 2]));

        $first->assertSee(__('app.bookmarks.saved', ['count' => 13]))
            ->assertSee(__('app.pagination.label'))
            ->assertDontSee($oldest->name);
        $this->assertSame(12, $first->viewData('listings')->count());
        $second->assertSee($oldest->name);
        $this->assertSame(1, $second->viewData('listings')->count());
    }

    public function test_hidden_bookmarked_listings_are_absent_uncounted_and_still_stored(): void
    {
        $user = User::factory()->create();
        $public = CarListing::factory()->approved()->create(['name' => 'Public Coupe DeVille']);
        $pending = CarListing::factory()->create(['name' => 'Pending Nomad']);
        $rejected = CarListing::factory()->rejected()->create(['name' => 'Rejected Skylark']);
        $suspended = CarListing::factory()->approved()->for(User::factory()->suspended(), 'owner')->create(['name' => 'Suspended Owner Corvair']);
        foreach ([$public, $pending, $rejected, $suspended] as $listing) {
            $this->bookmark($user, $listing, now()->subHour());
        }

        $response = $this->actingAs($user)->get(route('bookmarks.index'));

        $response->assertSee($public->name)
            ->assertSee(__('app.bookmarks.saved', ['count' => 1]))
            ->assertDontSee($pending->name)
            ->assertDontSee($rejected->name)
            ->assertDontSee($suspended->name);
        $this->assertSame(4, DB::table('user_bookmarks')->where('user_id', $user->id)->count());
    }

    public function test_a_listing_that_is_public_again_returns_to_its_original_position(): void
    {
        $user = User::factory()->create();
        $owner = User::factory()->suspended()->create();
        $first = CarListing::factory()->approved()->create(['name' => 'First Saved Studebaker']);
        $middle = CarListing::factory()->approved()->for($owner, 'owner')->create(['name' => 'Middle Saved Packard']);
        $last = CarListing::factory()->approved()->create(['name' => 'Last Saved Hudson']);
        $this->bookmark($user, $first, now()->subDays(3));
        $this->bookmark($user, $middle, now()->subDays(2));
        $this->bookmark($user, $last, now()->subDay());
        $this->actingAs($user);
        $this->get(route('bookmarks.index'))->assertDontSee($middle->name);

        $owner->forceFill(['status' => UserStatus::Active])->save();
        $response = $this->get(route('bookmarks.index'));

        $response->assertSeeInOrder([$last->name, $middle->name, $first->name]);
    }

    public function test_with_no_public_bookmarks_the_empty_box_links_to_the_listings_without_a_count_or_grid(): void
    {
        $user = User::factory()->create();
        $this->bookmark($user, CarListing::factory()->rejected()->create(), now()->subHour());

        $response = $this->actingAs($user)->get(route('bookmarks.index'));

        $response->assertSee(__('app.bookmarks.title'))
            ->assertSee(__('app.bookmarks.empty'))
            ->assertSee('<a href="'.route('listings.index').'" class="text-red underline underline-offset-4 hover:no-underline">'.__('app.bookmarks.browse').'</a>', false)
            ->assertDontSee(__('app.bookmarks.saved', ['count' => 0]))
            ->assertDontSee('md:grid-cols-3', false)
            ->assertDontSee(__('app.pagination.label'));
    }

    public function test_a_page_beyond_the_last_shows_the_header_and_pagination_without_cards_or_the_empty_box(): void
    {
        $user = User::factory()->create();
        $listing = CarListing::factory()->approved()->create(['name' => 'Only Saved Rambler']);
        $this->bookmark($user, $listing, now()->subHour());

        $response = $this->actingAs($user)->get(route('bookmarks.index', ['page' => 5]));

        $response->assertOk()
            ->assertSee(__('app.bookmarks.saved', ['count' => 1]))
            ->assertSee(__('app.pagination.label'))
            ->assertDontSee($listing->name)
            ->assertDontSee(__('app.bookmarks.empty'));
    }

    public function test_an_admin_sees_their_own_bookmarks_and_nobody_elses(): void
    {
        $admin = User::factory()->admin()->create();
        $mine = CarListing::factory()->approved()->create(['name' => 'Admin Saved Cadillac']);
        $theirs = CarListing::factory()->approved()->create(['name' => 'Member Saved Pontiac']);
        $this->bookmark($admin, $mine, now()->subHour());
        $this->bookmark(User::factory()->create(), $theirs, now()->subHour());

        $response = $this->actingAs($admin)->get(route('bookmarks.index'));

        $response->assertSee($mine->name)
            ->assertDontSee($theirs->name);
    }

    public function test_the_query_count_does_not_grow_with_the_bookmarked_listings(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        foreach (CarListing::factory()->approved()->withImages()->count(2)->create() as $listing) {
            $this->bookmark($user, $listing, now()->subHour());
        }
        $two = $this->queriesFor(route('bookmarks.index'));

        foreach (CarListing::factory()->approved()->withImages()->count(10)->create() as $listing) {
            $this->bookmark($user, $listing, now()->subMinute());
        }
        $twelve = $this->queriesFor(route('bookmarks.index'));

        $this->assertSame($two, $twelve);
    }

    private function bookmark(User $user, CarListing $listing, Carbon $at): void
    {
        $user->bookmarks()->attach($listing->id, ['created_at' => $at, 'updated_at' => $at]);
    }

    private function queriesFor(string $url): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get($url)->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }
}
