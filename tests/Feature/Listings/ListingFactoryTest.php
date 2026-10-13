<?php

namespace Tests\Feature\Listings;

use App\Enums\ListingStatus;
use App\Models\CarListing;
use App\Models\CarMaker;
use App\Models\CarModel;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ListingFactoryTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_a_default_listing_is_consistent_with_its_model(): void
    {
        $listing = CarListing::factory()->create();

        $carModel = CarModel::query()->findOrFail($listing->car_model_id);
        $this->assertSame($carModel->car_maker_id, $listing->car_maker_id);
        $this->assertSame($carModel->name, $listing->model);
        $this->assertTrue($carModel->coversYear($listing->year));
        $this->assertGreaterThanOrEqual(1900, $listing->year);
        $this->assertLessThanOrEqual(1989, $listing->year);
    }

    public function test_a_default_listing_is_pending_with_no_moderation(): void
    {
        $listing = CarListing::factory()->create()->fresh();

        $this->assertSame(ListingStatus::Pending, $listing->status);
        $this->assertNotNull($listing->submitted_at);
        $this->assertNull($listing->approved_at);
        $this->assertNull($listing->rejected_at);
        $this->assertNull($listing->rejection_reason);
        $this->assertNull($listing->moderated_by);
    }

    public function test_a_custom_model_listing_has_no_model_row_and_a_classic_year(): void
    {
        $listing = CarListing::factory()->customModel()->create();

        $this->assertNull($listing->car_model_id);
        $this->assertModelExists(CarMaker::query()->findOrFail($listing->car_maker_id));
        $this->assertNotSame('', $listing->model);
        $this->assertGreaterThanOrEqual(1900, $listing->year);
        $this->assertLessThanOrEqual(1989, $listing->year);
        $this->assertDatabaseCount('car_models', 0);
    }

    public function test_a_custom_model_listing_keeps_a_maker_given_with_for(): void
    {
        $maker = CarMaker::factory()->create();

        $listing = CarListing::factory()->customModel()->for($maker, 'maker')->create();

        $this->assertSame($maker->id, $listing->car_maker_id);
        $this->assertDatabaseCount('car_makers', 1);
    }

    public function test_for_car_model_uses_that_model_its_maker_its_name_and_its_years(): void
    {
        $carModel = CarModel::factory()->create(['name' => 'Bel Air', 'year_from' => 1955, 'year_to' => 1957]);

        $listing = CarListing::factory()->forCarModel($carModel)->create();

        $this->assertSame($carModel->id, $listing->car_model_id);
        $this->assertSame($carModel->car_maker_id, $listing->car_maker_id);
        $this->assertSame('Bel Air', $listing->model);
        $this->assertContains($listing->year, [1955, 1956, 1957]);
    }

    public function test_an_approved_listing_is_stamped_and_moderated_by_an_admin(): void
    {
        $listing = CarListing::factory()->approved()->create()->fresh();

        $this->assertSame(ListingStatus::Approved, $listing->status);
        $this->assertNotNull($listing->approved_at);
        $this->assertTrue(User::query()->findOrFail($listing->moderated_by)->isAdmin());
    }

    public function test_a_rejected_listing_carries_the_given_reason_or_a_default_one(): void
    {
        $withReason = CarListing::factory()->rejected('Blurry photos.')->create()->fresh();
        $withDefault = CarListing::factory()->rejected()->create()->fresh();

        $this->assertSame(ListingStatus::Rejected, $withReason->status);
        $this->assertNotNull($withReason->rejected_at);
        $this->assertTrue(User::query()->findOrFail($withReason->moderated_by)->isAdmin());
        $this->assertSame('Blurry photos.', $withReason->rejection_reason);
        $this->assertNotEmpty($withDefault->rejection_reason);
    }

    public function test_the_last_moderation_state_wins_without_leftover_columns(): void
    {
        $approved = CarListing::factory()->rejected()->approved()->create()->fresh();
        $rejected = CarListing::factory()->approved()->rejected()->create()->fresh();

        $this->assertNull($approved->rejected_at);
        $this->assertNull($approved->rejection_reason);
        $this->assertNull($rejected->approved_at);
    }

    public function test_with_images_creates_contiguous_positions_and_the_pinned_path_layout(): void
    {
        $listing = CarListing::factory()->withImages(3)->create();

        $images = $listing->images()->get();

        $this->assertSame([0, 1, 2], $images->pluck('position')->all());
        foreach ($images as $image) {
            $uuid = basename($image->path, '.webp');
            $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $uuid);
            $this->assertSame("listings/{$listing->id}/{$uuid}.webp", $image->path);
            $this->assertSame("listings/{$listing->id}/card/{$uuid}.webp", $image->card_path);
            $this->assertSame("listings/{$listing->id}/thumb/{$uuid}.webp", $image->thumb_path);
        }
        $this->assertCount(3, $images->pluck('path')->unique());
    }
}
