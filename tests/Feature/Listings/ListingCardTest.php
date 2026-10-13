<?php

namespace Tests\Feature\Listings;

use App\Enums\FuelType;
use App\Enums\ListingStatus;
use App\Models\CarListing;
use App\Models\CarMaker;
use App\Models\CarModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Testing\TestView;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ListingCardTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @return array<string, array{int, string}>
     */
    public static function prices(): array
    {
        return [
            'thousands' => [24500, '$24,500'],
            'millions' => [1250000, '$1,250,000'],
        ];
    }

    #[DataProvider('prices')]
    public function test_the_price_is_whole_dollars_with_comma_thousands(int $price, string $expected): void
    {
        $this->card(CarListing::factory()->approved()->create(['price' => $price]))
            ->assertSee($expected);
    }

    public function test_the_posted_line_is_a_human_relative_time(): void
    {
        $this->freezeTime();
        $listing = CarListing::factory()->approved()->create();

        $this->travel(3)->days();

        $this->card($listing)->assertSee(__('app.listings.card.posted', ['diff' => '3 days ago']));
    }

    public function test_the_card_shows_the_name_maker_model_year_and_fuel(): void
    {
        $maker = CarMaker::factory()->create(['name' => 'Chevrolet']);
        $carModel = CarModel::factory()->for($maker, 'maker')->create(['name' => 'Bel Air', 'year_from' => 1957, 'year_to' => 1957]);
        $listing = CarListing::factory()->approved()->forCarModel($carModel)->create([
            'name' => 'Turquoise dream',
            'fuel_type' => FuelType::Diesel,
        ]);

        $this->card($listing)
            ->assertSee('Turquoise dream')
            ->assertSee('Chevrolet · Bel Air')
            ->assertSee('1957')
            ->assertSee(FuelType::Diesel->label());
    }

    public function test_the_card_shows_the_primary_image_card_variant(): void
    {
        $listing = CarListing::factory()->approved()->withImages(2)->create(['name' => 'Bel Air']);
        $primary = $listing->images()->where('position', 0)->sole();

        $this->card($listing)
            ->assertSee('src="'.$primary->cardUrl().'"', false)
            ->assertSee('alt="Bel Air" width="800" height="600" loading="lazy"', false);
    }

    public function test_a_listing_without_images_shows_the_placeholder(): void
    {
        $html = (string) $this->card(CarListing::factory()->approved()->create());

        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringContainsString('<svg', $html);
    }

    public function test_details_links_to_the_detail_page_with_the_listing_name_after_the_visible_text(): void
    {
        $listing = CarListing::factory()->approved()->create(['name' => 'Bel Air']);

        $this->card($listing)
            ->assertSee('href="'.route('listings.show', $listing).'"', false)
            ->assertSee('>Details<span class="sr-only"> Bel Air</span></a>', false);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function cardPages(): array
    {
        return [
            'home' => ['home'],
            'index' => ['listings.index'],
            'advanced search' => ['listings.advanced'],
        ];
    }

    #[DataProvider('cardPages')]
    public function test_every_card_page_links_its_cards_to_the_detail_page(string $page): void
    {
        $this->withoutVite();
        $listing = CarListing::factory()->approved()->create();

        $this->get(route($page))->assertSee('href="'.route('listings.show', $listing).'"', false);
    }

    /**
     * @return array<string, array{ListingStatus, bool}>
     */
    public static function statuses(): array
    {
        return [
            'pending shows a stamp' => [ListingStatus::Pending, true],
            'rejected shows a stamp' => [ListingStatus::Rejected, true],
            'approved shows none' => [ListingStatus::Approved, false],
        ];
    }

    #[DataProvider('statuses')]
    public function test_the_status_variant_stamps_pending_and_rejected_only(ListingStatus $status, bool $stamped): void
    {
        $listing = $this->listingWithStatus($status);

        $card = $this->card($listing, showStatus: true);

        if ($stamped) {
            $card->assertSee($status->label());
        } else {
            $card->assertDontSee($status->label());
        }
    }

    #[DataProvider('statuses')]
    public function test_without_the_status_variant_no_status_renders(ListingStatus $status): void
    {
        $this->card($this->listingWithStatus($status))
            ->assertDontSee($status->label())
            ->assertDontSee('Rusted sills');
    }

    public function test_a_rejected_listing_shows_its_reason_under_the_stamp(): void
    {
        $this->card($this->listingWithStatus(ListingStatus::Rejected), showStatus: true)
            ->assertSeeInOrder([ListingStatus::Rejected->label(), 'Rusted sills']);
    }

    /**
     * @return array<string, array{ListingStatus, string}>
     */
    public static function statusTones(): array
    {
        return [
            'pending' => [ListingStatus::Pending, 'border-status-pending text-status-pending'],
            'approved' => [ListingStatus::Approved, 'border-status-approved text-status-approved'],
            'rejected' => [ListingStatus::Rejected, 'border-status-rejected text-status-rejected'],
        ];
    }

    #[DataProvider('statusTones')]
    public function test_the_status_stamp_renders_the_label_in_its_status_tone(ListingStatus $status, string $toneClasses): void
    {
        $this->blade('<x-listings.status-stamp :status="$status" />', ['status' => $status])
            ->assertSee($toneClasses, false)
            ->assertSee($status->label());
    }

    private function listingWithStatus(ListingStatus $status): CarListing
    {
        $factory = CarListing::factory();

        return match ($status) {
            ListingStatus::Pending => $factory->create(['name' => 'Bel Air']),
            ListingStatus::Approved => $factory->approved()->create(['name' => 'Bel Air']),
            ListingStatus::Rejected => $factory->rejected('Rusted sills')->create(['name' => 'Bel Air']),
        };
    }

    private function card(CarListing $listing, bool $showStatus = false): TestView
    {
        return $this->blade(
            '<x-listings.card :listing="$listing" :show-status="$showStatus" />',
            ['listing' => $listing->load(['maker', 'primaryImage']), 'showStatus' => $showStatus],
        );
    }
}
