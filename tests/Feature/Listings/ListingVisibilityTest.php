<?php

namespace Tests\Feature\Listings;

use App\Enums\UserStatus;
use App\Models\CarListing;
use App\Models\User;
use Illuminate\Database\LazyLoadingViolationException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ListingVisibilityTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @return array<string, array{string, bool, bool}>
     */
    public static function statusAndOwnerStatus(): array
    {
        return [
            'pending, active owner' => ['pending', false, false],
            'approved, active owner' => ['approved', false, true],
            'rejected, active owner' => ['rejected', false, false],
            'pending, suspended owner' => ['pending', true, false],
            'approved, suspended owner' => ['approved', true, false],
            'rejected, suspended owner' => ['rejected', true, false],
        ];
    }

    #[DataProvider('statusAndOwnerStatus')]
    public function test_the_scope_returns_only_approved_listings_of_active_owners(string $status, bool $ownerSuspended, bool $expected): void
    {
        $listing = $this->listing($status, $ownerSuspended);

        $visible = CarListing::query()->publiclyVisible()->whereKey($listing->id)->exists();

        $this->assertSame($expected, $visible);
    }

    #[DataProvider('statusAndOwnerStatus')]
    public function test_is_public_agrees_with_the_scope(string $status, bool $ownerSuspended, bool $expected): void
    {
        $listing = $this->listing($status, $ownerSuspended);

        $public = CarListing::query()->with('owner')->findOrFail($listing->id)->isPublic();

        $this->assertSame($expected, $public);
    }

    public function test_the_scope_does_not_load_the_owner(): void
    {
        $this->listing('approved', false);

        $listings = CarListing::query()->publiclyVisible()->get();

        $this->assertCount(1, $listings);
        $this->assertFalse($listings->first()->relationLoaded('owner'));
    }

    public function test_is_public_refuses_to_lazy_load_the_owner(): void
    {
        $this->listing('approved', false);
        $this->listing('approved', false);
        $listing = CarListing::query()->get()->first();

        $this->expectException(LazyLoadingViolationException::class);

        $listing->isPublic();
    }

    public function test_is_public_fails_closed_when_the_owner_status_was_not_selected(): void
    {
        $this->listing('approved', false);

        $listing = CarListing::query()->with('owner:id')->sole();

        $this->assertFalse($listing->isPublic());
    }

    public function test_the_scope_still_applies_when_the_query_joins_users(): void
    {
        $this->listing('approved', false);
        $this->listing('pending', false);

        $visible = CarListing::query()->join('users', 'users.id', '=', 'car_listings.user_id')->publiclyVisible()->count();

        $this->assertSame(1, $visible);
    }

    public function test_suspending_and_reactivating_the_owner_leaves_their_listings_untouched(): void
    {
        $owner = User::factory()->create();
        $listings = collect([
            CarListing::factory()->for($owner, 'owner')->create(),
            CarListing::factory()->approved()->for($owner, 'owner')->create(),
            CarListing::factory()->rejected()->for($owner, 'owner')->create(),
        ]);
        $before = $listings->map(fn (CarListing $listing) => $listing->fresh()->getAttributes())->all();

        $owner->forceFill(['status' => UserStatus::Suspended])->save();
        $visibleWhileSuspended = CarListing::query()->publiclyVisible()->count();
        $owner->forceFill(['status' => UserStatus::Active])->save();

        $this->assertSame(0, $visibleWhileSuspended);
        $this->assertSame(1, CarListing::query()->publiclyVisible()->count());
        $this->assertSame($before, $listings->map(fn (CarListing $listing) => $listing->fresh()->getAttributes())->all());
    }

    public function test_is_owned_by_is_true_only_for_the_owner(): void
    {
        $listing = CarListing::factory()->create();
        $owner = User::query()->findOrFail($listing->user_id);
        $stranger = User::factory()->create();

        $this->assertTrue($listing->isOwnedBy($owner));
        $this->assertFalse($listing->isOwnedBy($stranger));
        $this->assertFalse($listing->isOwnedBy(null));
    }

    private function listing(string $status, bool $ownerSuspended): CarListing
    {
        $owner = $ownerSuspended ? User::factory()->suspended()->create() : User::factory()->create();
        $factory = CarListing::factory()->for($owner, 'owner');

        return match ($status) {
            'pending' => $factory->create(),
            'approved' => $factory->approved()->create(),
            'rejected' => $factory->rejected()->create(),
        };
    }
}
