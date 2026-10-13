<?php

namespace Tests\Feature\Bookmarks;

use App\Models\CarListing;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BookmarkFlowTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @return array<string, array{string}>
     */
    public static function allowedViewers(): array
    {
        return [
            'app user' => ['appUser'],
            'suspended app user' => ['suspended'],
            'admin' => ['admin'],
        ];
    }

    #[DataProvider('allowedViewers')]
    public function test_a_viewer_bookmarks_a_public_listing_and_returns_to_its_detail_fallback(string $viewer): void
    {
        $user = $this->viewer($viewer);
        $listing = CarListing::factory()->approved()->create();

        $response = $this->actingAs($user)->post(route('bookmarks.store', $listing));

        $response->assertRedirect(route('listings.show', $listing))
            ->assertSessionHas('success', __('app.bookmarks.flash.added'));
        $this->assertDatabaseHas('user_bookmarks', ['user_id' => $user->id, 'car_listing_id' => $listing->id]);
    }

    #[DataProvider('allowedViewers')]
    public function test_a_viewer_removes_a_bookmark_and_returns_to_the_bookmarks_fallback(string $viewer): void
    {
        $user = $this->viewer($viewer);
        $listing = CarListing::factory()->approved()->create();
        $user->bookmarks()->attach($listing->id);

        $response = $this->actingAs($user)->delete(route('bookmarks.destroy', $listing));

        $response->assertRedirect(route('bookmarks.index'))
            ->assertSessionHas('success', __('app.bookmarks.flash.removed'));
        $this->assertDatabaseMissing('user_bookmarks', ['user_id' => $user->id, 'car_listing_id' => $listing->id]);
    }

    public function test_bookmarking_twice_keeps_one_row_with_its_original_time_and_flashes_success(): void
    {
        $user = User::factory()->create();
        $listing = CarListing::factory()->approved()->create();
        $user->bookmarks()->attach($listing->id, ['created_at' => '2026-01-01 10:00:00', 'updated_at' => '2026-01-01 10:00:00']);

        $response = $this->actingAs($user)->post(route('bookmarks.store', $listing));

        $response->assertSessionHas('success', __('app.bookmarks.flash.added'));
        $this->assertSame(1, DB::table('user_bookmarks')->count());
        $this->assertSame('2026-01-01 10:00:00', DB::table('user_bookmarks')->value('created_at'));
    }

    public function test_removing_a_listing_that_is_not_bookmarked_changes_nothing_and_flashes_success(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $listing = CarListing::factory()->approved()->create();
        $other->bookmarks()->attach($listing->id);

        $response = $this->actingAs($user)->delete(route('bookmarks.destroy', $listing));

        $response->assertSessionHas('success', __('app.bookmarks.flash.removed'))
            ->assertSessionMissing('error');
        $this->assertDatabaseHas('user_bookmarks', ['user_id' => $other->id, 'car_listing_id' => $listing->id]);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function ownerWrites(): array
    {
        $cases = [];

        foreach (['approved', 'pending', 'rejected', 'suspended owner'] as $state) {
            foreach (['POST', 'DELETE'] as $method) {
                $cases["{$method} on own {$state} listing"] = [$state, $method];
            }
        }

        return $cases;
    }

    #[DataProvider('ownerWrites')]
    public function test_the_owner_is_forbidden_on_both_verbs_in_every_status(string $state, string $method): void
    {
        $listing = $this->listingIn($state);
        $owner = $listing->owner;

        $response = $this->actingAs($owner)->call($method, route($method === 'POST' ? 'bookmarks.store' : 'bookmarks.destroy', $listing));

        $response->assertForbidden();
        $this->assertSame(0, DB::table('user_bookmarks')->count());
    }

    /**
     * @return array<string, array{string, string, string, int}>
     */
    public static function refusedWrites(): array
    {
        $cases = [];

        foreach (['pending', 'rejected', 'suspended owner'] as $state) {
            foreach (['POST', 'DELETE'] as $method) {
                $cases["{$method} on {$state} as stranger"] = [$state, $method, 'appUser', 404];
                $cases["{$method} on {$state} as admin"] = [$state, $method, 'admin', 403];
            }
        }

        return $cases;
    }

    #[DataProvider('refusedWrites')]
    public function test_a_non_public_listing_is_refused_without_touching_the_rows(string $state, string $method, string $viewer, int $status): void
    {
        $listing = $this->listingIn($state);
        $user = $this->viewer($viewer);
        $user->bookmarks()->attach($listing->id, ['created_at' => '2026-01-01 10:00:00', 'updated_at' => '2026-01-01 10:00:00']);

        $response = $this->actingAs($user)->call($method, route($method === 'POST' ? 'bookmarks.store' : 'bookmarks.destroy', $listing));

        $response->assertStatus($status);
        $this->assertSame(1, DB::table('user_bookmarks')->count());
        $this->assertSame('2026-01-01 10:00:00', DB::table('user_bookmarks')->value('created_at'));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function writes(): array
    {
        return [
            'store' => ['POST', 'bookmarks.store'],
            'destroy' => ['DELETE', 'bookmarks.destroy'],
        ];
    }

    #[DataProvider('writes')]
    public function test_each_write_returns_to_a_same_host_referer(string $method, string $name): void
    {
        $listing = CarListing::factory()->approved()->create();
        $referer = route('bookmarks.index', ['page' => 2]);

        $response = $this->actingAs(User::factory()->create())
            ->call($method, route($name, $listing), server: $this->transformHeadersToServerVars(['Referer' => $referer]));

        $response->assertRedirect($referer);
    }

    #[DataProvider('writes')]
    public function test_a_guest_is_sent_to_login_by_each_write(string $method, string $name): void
    {
        $listing = CarListing::factory()->approved()->create();

        $response = $this->call($method, route($name, $listing));

        $response->assertRedirect(route('login'));
        $this->assertSame(0, DB::table('user_bookmarks')->count());
    }

    public function test_a_guest_is_sent_to_login_by_the_bookmarks_page(): void
    {
        $this->get(route('bookmarks.index'))->assertRedirect(route('login'));
    }

    public function test_an_unknown_listing_is_not_found(): void
    {
        $response = $this->actingAs(User::factory()->create())->post(route('bookmarks.store', ['listing' => 999999]));

        $response->assertNotFound();
    }

    public function test_a_non_numeric_listing_key_is_not_a_route(): void
    {
        $response = $this->actingAs(User::factory()->create())->post('/bookmarks/abc');

        $response->assertNotFound();
    }

    private function viewer(string $viewer): User
    {
        return match ($viewer) {
            'appUser' => User::factory()->create(),
            'suspended' => User::factory()->suspended()->create(),
            'admin' => User::factory()->admin()->create(),
        };
    }

    private function listingIn(string $state): CarListing
    {
        $owner = $state === 'suspended owner' ? User::factory()->suspended()->create() : User::factory()->create();
        $factory = CarListing::factory()->for($owner, 'owner');

        return match ($state) {
            'approved', 'suspended owner' => $factory->approved()->create(),
            'pending' => $factory->create(),
            'rejected' => $factory->rejected()->create(),
        };
    }
}
