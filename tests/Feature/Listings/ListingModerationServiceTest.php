<?php

namespace Tests\Feature\Listings;

use App\Enums\ListingStatus;
use App\Exceptions\InvalidListingStatusTransitionException;
use App\Exceptions\StaleListingReviewException;
use App\Models\CarListing;
use App\Models\User;
use App\Services\ListingModerationService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ListingModerationServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @return array<string, array{string}>
     */
    public static function moderatedStates(): array
    {
        return [
            'approved' => ['approved'],
            'rejected' => ['rejected'],
        ];
    }

    #[DataProvider('moderatedStates')]
    public function test_return_to_pending_resubmits_a_moderated_listing(string $state): void
    {
        $this->freezeSecond();
        $listing = CarListing::factory()->{$state}()->create();
        $moderatorId = $listing->moderated_by;
        $this->travel(1)->hours();

        app(ListingModerationService::class)->returnToPending($listing);

        $listing->refresh();
        $this->assertSame(ListingStatus::Pending, $listing->status);
        $this->assertEquals(now(), $listing->submitted_at);
        $this->assertNull($listing->approved_at);
        $this->assertNull($listing->rejected_at);
        $this->assertNull($listing->rejection_reason);
        $this->assertSame($moderatorId, $listing->moderated_by);
    }

    public function test_return_to_pending_leaves_a_pending_listing_untouched(): void
    {
        $this->freezeSecond();
        $listing = CarListing::factory()->create();
        $before = $listing->fresh()->getAttributes();
        $this->travel(1)->hours();

        app(ListingModerationService::class)->returnToPending($listing);

        $this->assertSame($before, $listing->fresh()->getAttributes());
    }

    public function test_approve_publishes_a_pending_listing(): void
    {
        $this->freezeSecond();
        $listing = CarListing::factory()->create([
            'submitted_at' => now()->subDays(2),
            'rejected_at' => now()->subDays(3),
            'rejection_reason' => 'An old reason.',
        ]);
        $admin = User::factory()->admin()->create();
        $this->travel(1)->hours();

        app(ListingModerationService::class)->approve($listing, $admin, $listing->moderationVersion());

        $listing->refresh();
        $this->assertSame(ListingStatus::Approved, $listing->status);
        $this->assertEquals(now(), $listing->approved_at);
        $this->assertNull($listing->rejected_at);
        $this->assertNull($listing->rejection_reason);
        $this->assertSame($admin->id, $listing->moderated_by);
        $this->assertEquals(now()->subHour()->subDays(2), $listing->submitted_at);
    }

    public function test_reject_stores_the_reason_on_a_pending_listing(): void
    {
        $this->freezeSecond();
        $listing = CarListing::factory()->create(['submitted_at' => now()->subDays(2), 'approved_at' => now()->subDays(3)]);
        $admin = User::factory()->admin()->create();
        $this->travel(1)->hours();

        app(ListingModerationService::class)->reject($listing, $admin, 'Blurry photos.', $listing->moderationVersion());

        $listing->refresh();
        $this->assertSame(ListingStatus::Rejected, $listing->status);
        $this->assertEquals(now(), $listing->rejected_at);
        $this->assertSame('Blurry photos.', $listing->rejection_reason);
        $this->assertNull($listing->approved_at);
        $this->assertSame($admin->id, $listing->moderated_by);
        $this->assertEquals(now()->subHour()->subDays(2), $listing->submitted_at);
    }

    #[DataProvider('moderatedStates')]
    public function test_approve_refuses_a_listing_that_is_not_pending(string $state): void
    {
        $listing = CarListing::factory()->{$state}()->create();
        $before = $listing->fresh()->getAttributes();

        try {
            app(ListingModerationService::class)->approve($listing, User::factory()->admin()->create(), $listing->moderationVersion());
        } catch (InvalidListingStatusTransitionException $exception) {
        }

        $this->assertInstanceOf(InvalidListingStatusTransitionException::class, $exception ?? null);
        $this->assertSame($before, $listing->fresh()->getAttributes());
    }

    #[DataProvider('moderatedStates')]
    public function test_reject_refuses_a_listing_that_is_not_pending(string $state): void
    {
        $listing = CarListing::factory()->{$state}()->create();
        $before = $listing->fresh()->getAttributes();

        try {
            app(ListingModerationService::class)->reject($listing, User::factory()->admin()->create(), 'Late.', $listing->moderationVersion());
        } catch (InvalidListingStatusTransitionException $exception) {
        }

        $this->assertInstanceOf(InvalidListingStatusTransitionException::class, $exception ?? null);
        $this->assertSame($before, $listing->fresh()->getAttributes());
    }

    public function test_the_guard_reads_the_locked_row_not_the_stale_instance(): void
    {
        $stale = CarListing::factory()->create();
        $version = $stale->moderationVersion();
        $admin = User::factory()->admin()->create();
        app(ListingModerationService::class)->approve(CarListing::findOrFail($stale->id), $admin, $version);
        $before = $stale->fresh()->getAttributes();

        try {
            app(ListingModerationService::class)->reject($stale, $admin, 'Too late.', $version);
        } catch (InvalidListingStatusTransitionException $exception) {
        }

        $this->assertInstanceOf(InvalidListingStatusTransitionException::class, $exception ?? null);
        $this->assertSame($before, $stale->fresh()->getAttributes());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function transitions(): array
    {
        return [
            'approve' => ['approve'],
            'reject' => ['reject'],
        ];
    }

    #[DataProvider('transitions')]
    public function test_a_listing_changed_since_review_is_refused(string $transition): void
    {
        $this->freezeSecond();
        $listing = CarListing::factory()->create();
        $version = $listing->moderationVersion();
        $this->travel(5)->seconds();
        $listing->update(['name' => 'Edited after review']);
        $before = $listing->fresh()->getAttributes();

        try {
            $this->moderate($transition, $listing, $version);
        } catch (StaleListingReviewException $exception) {
        }

        $this->assertInstanceOf(StaleListingReviewException::class, $exception ?? null);
        $this->assertSame($before, $listing->fresh()->getAttributes());
    }

    #[DataProvider('transitions')]
    public function test_the_status_guard_wins_over_a_stale_version(string $transition): void
    {
        $listing = CarListing::factory()->approved()->create();

        $this->expectException(InvalidListingStatusTransitionException::class);

        $this->moderate($transition, $listing, $listing->moderationVersion() - 60);
    }

    private function moderate(string $transition, CarListing $listing, int $version): void
    {
        $admin = User::factory()->admin()->create();
        $moderation = app(ListingModerationService::class);

        $transition === 'approve'
            ? $moderation->approve($listing, $admin, $version)
            : $moderation->reject($listing, $admin, 'A reason.', $version);
    }
}
