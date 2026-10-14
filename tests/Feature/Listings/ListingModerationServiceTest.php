<?php

namespace Tests\Feature\Listings;

use App\Enums\ListingStatus;
use App\Models\CarListing;
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
}
