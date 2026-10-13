<?php

namespace Tests\Unit;

use App\Enums\ListingStatus;
use App\Models\CarListing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CarListingTest extends TestCase
{
    /**
     * @return array<string, array{ListingStatus, array<string, bool>}>
     */
    public static function statuses(): array
    {
        return [
            'pending' => [ListingStatus::Pending, [
                'isPending' => true,
                'isApproved' => false,
                'isRejected' => false,
                'canBeApproved' => true,
                'canBeRejected' => true,
                'shouldReturnToPendingOnEdit' => false,
            ]],
            'approved' => [ListingStatus::Approved, [
                'isPending' => false,
                'isApproved' => true,
                'isRejected' => false,
                'canBeApproved' => false,
                'canBeRejected' => false,
                'shouldReturnToPendingOnEdit' => true,
            ]],
            'rejected' => [ListingStatus::Rejected, [
                'isPending' => false,
                'isApproved' => false,
                'isRejected' => true,
                'canBeApproved' => false,
                'canBeRejected' => false,
                'shouldReturnToPendingOnEdit' => true,
            ]],
        ];
    }

    /**
     * @param  array<string, bool>  $expected
     */
    #[DataProvider('statuses')]
    public function test_predicates_and_guards_follow_the_status(ListingStatus $status, array $expected): void
    {
        $listing = new CarListing;
        $listing->status = $status;

        $answers = array_map(fn (string $method) => $listing->{$method}(), array_combine(array_keys($expected), array_keys($expected)));

        $this->assertSame($expected, $answers);
    }

    public function test_a_listing_without_a_model_row_has_a_custom_model(): void
    {
        $custom = new CarListing(['car_model_id' => null, 'model' => 'Roadster Special']);
        $listed = new CarListing(['car_model_id' => 12, 'model' => 'Bel Air']);

        $this->assertTrue($custom->hasCustomModel());
        $this->assertFalse($listed->hasCustomModel());
    }
}
