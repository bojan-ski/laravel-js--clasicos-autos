<?php

namespace Tests\Feature;

use App\Enums\ListingStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\CarListing;
use App\Models\User;
use Database\Seeders\CarMakerSeeder;
use Database\Seeders\CarModelSeeder;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\ReviewSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use LogicException;
use Tests\TestCase;

class ReviewSeederTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['auth.admin.email' => 'boss@example.com', 'auth.admin.password' => 'initial1']);
    }

    public function test_it_seeds_two_pages_of_public_listings_across_several_seeded_makers(): void
    {
        $this->seed([DatabaseSeeder::class, ReviewSeeder::class]);

        $public = CarListing::query()->publiclyVisible()->get();

        $this->assertGreaterThanOrEqual(13, $public->count());
        $this->assertGreaterThanOrEqual(4, $public->pluck('car_maker_id')->unique()->count());
        $this->assertFalse($public->contains(fn (CarListing $listing): bool => $listing->car_model_id === null));
        $this->assertSame(1, $public->filter(fn (CarListing $listing): bool => $listing->images()->exists())->count());
    }

    public function test_the_review_user_owns_a_pending_and_a_rejected_listing_with_a_reason(): void
    {
        $this->seed([DatabaseSeeder::class, ReviewSeeder::class]);

        $reviewer = User::query()->where('email', 'review@example.com')->sole();

        $this->assertSame(1, $reviewer->listings()->where('status', ListingStatus::Pending)->count());
        $rejected = $reviewer->listings()->where('status', ListingStatus::Rejected)->sole();
        $this->assertNotEmpty($rejected->rejection_reason);
    }

    public function test_a_suspended_owner_has_one_approved_listing_that_is_not_public(): void
    {
        $this->seed([DatabaseSeeder::class, ReviewSeeder::class]);

        $owner = User::query()->where('status', UserStatus::Suspended)->sole();

        $listing = $owner->listings()->sole();
        $this->assertSame(ListingStatus::Approved, $listing->status);
        $this->assertFalse(CarListing::query()->publiclyVisible()->whereKey($listing->id)->exists());
    }

    public function test_approved_listings_are_moderated_by_the_seeded_admin(): void
    {
        $this->seed([DatabaseSeeder::class, ReviewSeeder::class]);

        $admin = User::query()->where('role', UserRole::Admin)->sole();

        $this->assertSame([$admin->id], CarListing::query()->where('status', ListingStatus::Approved)->distinct()->pluck('moderated_by')->all());
    }

    public function test_the_review_user_is_an_active_app_user_who_can_sign_in(): void
    {
        $this->seed([DatabaseSeeder::class, ReviewSeeder::class]);

        $this->post(route('login.store'), ['email' => 'review@example.com', 'password' => 'password'])
            ->assertRedirect(route('profile'));
        $this->assertAuthenticatedAs(User::query()->where('email', 'review@example.com')->sole());
    }

    public function test_without_an_admin_the_moderated_listings_have_no_moderator(): void
    {
        $this->seed([CarMakerSeeder::class, CarModelSeeder::class, ReviewSeeder::class]);

        $this->assertSame(0, User::query()->where('role', UserRole::Admin)->count());
        $this->assertSame([null], CarListing::query()->distinct()->pluck('moderated_by')->all());
    }

    public function test_a_second_run_adds_nothing(): void
    {
        $this->seed([DatabaseSeeder::class, ReviewSeeder::class]);
        $users = User::query()->count();
        $listings = CarListing::query()->count();

        $this->seed(ReviewSeeder::class);

        $this->assertSame($users, User::query()->count());
        $this->assertSame($listings, CarListing::query()->count());
    }

    public function test_it_refuses_to_run_without_the_seeded_makers_and_models(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('migrate:fresh --seed');

        $this->seed(ReviewSeeder::class);
    }
}
