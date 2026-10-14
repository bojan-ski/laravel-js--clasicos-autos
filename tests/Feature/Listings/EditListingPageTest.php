<?php

namespace Tests\Feature\Listings;

use App\Enums\BodyType;
use App\Enums\DocumentationStatus;
use App\Enums\EngineCondition;
use App\Enums\EngineHistory;
use App\Enums\FuelType;
use App\Enums\ListingStatus;
use App\Enums\Odometer;
use App\Enums\RestorationHistory;
use App\Enums\Transmission;
use App\Models\CarListing;
use App\Models\CarMaker;
use App\Models\CarModel;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EditListingPageTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_a_guest_is_sent_to_login(): void
    {
        $listing = CarListing::factory()->approved()->create();

        $this->get(route('listings.edit', $listing))->assertRedirect(route('login'));
    }

    public function test_a_stranger_gets_the_403_page_on_a_public_listing(): void
    {
        $listing = CarListing::factory()->approved()->create();

        $this->actingAs(User::factory()->create())->get(route('listings.edit', $listing))
            ->assertForbidden()
            ->assertSee(__('app.errors.403.title'));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function nonPublicListings(): array
    {
        return [
            'pending' => ['pending'],
            'rejected' => ['rejected'],
            'suspended owner' => ['suspended owner'],
        ];
    }

    #[DataProvider('nonPublicListings')]
    public function test_a_stranger_gets_the_404_page_on_a_non_public_listing(string $state): void
    {
        $listing = match ($state) {
            'pending' => CarListing::factory()->create(),
            'rejected' => CarListing::factory()->rejected()->create(),
            'suspended owner' => CarListing::factory()->approved()->for(User::factory()->suspended(), 'owner')->create(),
        };

        $this->actingAs(User::factory()->create())->get(route('listings.edit', $listing))
            ->assertNotFound()
            ->assertSee(__('app.errors.404.title'));
    }

    /**
     * @return array<string, array{string, ListingStatus}>
     */
    public static function allowedViewers(): array
    {
        return [
            'owner, pending' => ['owner', ListingStatus::Pending],
            'owner, approved' => ['owner', ListingStatus::Approved],
            'owner, rejected' => ['owner', ListingStatus::Rejected],
            'suspended owner' => ['suspended owner', ListingStatus::Approved],
            'admin, pending' => ['admin', ListingStatus::Pending],
            'admin, approved' => ['admin', ListingStatus::Approved],
        ];
    }

    #[DataProvider('allowedViewers')]
    public function test_the_owner_and_an_admin_reach_the_page(string $viewer, ListingStatus $status): void
    {
        $owner = $viewer === 'suspended owner' ? User::factory()->suspended()->create() : User::factory()->create();
        $listing = $this->listing($status, $owner);
        $actor = $viewer === 'admin' ? User::factory()->admin()->create() : $owner;

        $this->actingAs($actor)->get(route('listings.edit', $listing))
            ->assertOk()
            ->assertViewIs('pages.listings.edit');
    }

    public function test_an_unknown_listing_is_a_404(): void
    {
        $this->actingAs(User::factory()->admin()->create())->get(route('listings.edit', 999999))->assertNotFound();
    }

    public function test_the_page_renders_the_back_link_segment_header_and_thumbnail(): void
    {
        $listing = CarListing::factory()->approved()->withImages(2)->create(['name' => 'Sunset Bel Air']);
        $html = $this->page($listing)->getContent();

        $this->assertMatchesRegularExpression('/<a href="'.preg_quote(route('listings.show', $listing), '/').'" class="inline-flex size-11/', $html);
        $this->assertMatchesRegularExpression('/<nav aria-label="Edit listing sections"[^>]*>\s*<a href="'.preg_quote(route('listings.edit', $listing), '/').'"\s+aria-current="page"[^>]*>Edit Listing<\/a>\s*<a href="'.preg_quote(route('listings.images.edit', $listing), '/').'"\s+class="[^"]*"\s*>Edit Images<\/a>\s*<\/nav>/', $html);
        $this->assertSame(1, substr_count($html, 'aria-current="page"'));
        $this->assertMatchesRegularExpression('/<[^>]+>\s*Sunset Bel Air\s*<\/[a-z]+>\s*<h1[^>]*>Edit Car Listing Data<\/h1>/', $html);
        $this->assertStringContainsString('<img src="'.e($listing->primaryImage->thumbUrl()).'" alt="" width="320" height="240"', $html);
    }

    public function test_a_listing_without_images_renders_no_thumbnail(): void
    {
        $html = $this->page(CarListing::factory()->approved()->create())->getContent();

        $this->assertStringNotContainsString('<img ', $html);
    }

    public function test_the_six_sections_render_with_the_rail_and_a_description_only_section(): void
    {
        $html = $this->page(CarListing::factory()->create())->getContent();

        preg_match_all('/<fieldset id="([^"]+)" data-form-section/', $html, $sections);
        $this->assertSame(['section-car', 'section-engine', 'section-history', 'section-looks', 'section-location', 'section-description'], $sections[1]);

        preg_match_all('/<a href="#(section-[a-z]+)"/', $html, $anchors);
        $this->assertSame($sections[1], $anchors[1]);

        $this->assertMatchesRegularExpression('/VI\.<\/span>\s*<span>Description<\/span>/', $html);
        $this->assertStringNotContainsString('Description &amp; photos', $html);
        $this->assertMatchesRegularExpression('/<textarea id="description"[^>]*maxlength="5000"/', $html);
    }

    public function test_the_form_puts_to_update_without_photos_progress_or_enctype(): void
    {
        $listing = CarListing::factory()->create();
        $html = $this->page($listing)->getContent();

        $this->assertMatchesRegularExpression('/<form method="POST" action="'.preg_quote(route('listings.update', $listing), '/').'" class="flex flex-col">/', $html);
        $this->assertStringContainsString('<input type="hidden" name="_method" value="PUT">', $html);
        $this->assertStringNotContainsString('enctype', $html);
        $this->assertStringNotContainsString('type="file"', $html);
        $this->assertStringNotContainsString('data-form-progress', $html);
    }

    public function test_the_footer_shows_last_updated_cancel_and_the_sticky_submit(): void
    {
        $listing = CarListing::factory()->create(['updated_at' => '2026-03-05 10:00:00']);
        $html = $this->page($listing)->getContent();

        $this->assertStringContainsString('Last updated 05 March 2026', $html);
        $this->assertMatchesRegularExpression('/<a href="'.preg_quote(route('listings.show', $listing), '/').'"[^>]*border-transparent[^>]*>\s*Cancel\s*<\/a>/', $html);
        $this->assertSame(2, substr_count($html, 'Update Car Listing'));

        preg_match('/<div data-sticky-bar class="([^"]*)"/', $html, $bar);
        $this->assertStringStartsWith('sticky bottom-(--cookie-banner-h,0px) z-10 ', $bar[1]);
        $this->assertStringContainsString('md:hidden', $bar[1]);
        $this->assertStringNotContainsString('bottom-0', $bar[1]);
    }

    public function test_every_field_shows_the_listings_value_with_enums_selected_by_value(): void
    {
        $listing = CarListing::factory()->create([
            'name' => 'Sunset Bel Air',
            'price' => 25000,
            'year' => 1957,
            'mileage' => 120000,
            'transmission' => Transmission::Manual,
            'fuel_type' => FuelType::Gasoline,
            'engine_size' => '4.6L',
            'engine_history' => EngineHistory::RebuiltEngine,
            'engine_condition' => EngineCondition::cases()[0],
            'restoration_history' => RestorationHistory::cases()[0],
            'original_parts_percentage' => 0,
            'odometer' => Odometer::cases()[0],
            'documentation_status' => DocumentationStatus::cases()[0],
            'body_type' => BodyType::cases()[0],
            'exterior_color' => null,
            'location_city' => 'Havana',
            'description' => 'Two-tone paint',
        ]);
        $html = $this->page($listing)->getContent();

        foreach (['name' => 'Sunset Bel Air', 'price' => '25000', 'year' => '1957', 'mileage' => '120000', 'engine_size' => '4.6L', 'original_parts_percentage' => '0', 'location_city' => 'Havana'] as $field => $value) {
            $this->assertMatchesRegularExpression('/<input id="'.$field.'"[^>]*value="'.preg_quote($value, '/').'"/', $html, $field);
        }

        $this->assertDoesNotMatchRegularExpression('/<input id="exterior_color"[^>]*value=/', $html);
        $this->assertMatchesRegularExpression('/<textarea id="description"[^>]*>Two-tone paint<\/textarea>/', $html);
        $this->assertSame((string) $listing->car_maker_id, $this->selected($html, 'car_maker_id'));
        $this->assertSame((string) $listing->car_model_id, $this->selected($html, 'car_model_id'));
        $this->assertSame(Transmission::Manual->value, $this->selected($html, 'transmission'));
        $this->assertSame(EngineHistory::RebuiltEngine->value, $this->selected($html, 'engine_history'));
        $this->assertSame(BodyType::cases()[0]->value, $this->selected($html, 'body_type'));
        $this->assertNull($this->selected($html, 'seat_material'));
        $this->assertDoesNotMatchRegularExpression('/<input id="model"[^>]*value=/', $html);
    }

    public function test_a_custom_model_listing_preselects_other_with_its_typed_model(): void
    {
        $listing = CarListing::factory()->customModel()->create(['model' => 'Custom Roadster']);
        $html = $this->page($listing)->getContent();

        $this->assertSame('other', $this->selected($html, 'car_model_id'));
        $this->assertMatchesRegularExpression('/<input id="model"[^>]*value="Custom Roadster"/', $html);
    }

    public function test_after_a_failed_save_the_posted_values_win_over_the_listings(): void
    {
        $listing = CarListing::factory()->create(['name' => 'Stored name']);
        $owner = $listing->owner;

        $html = $this->actingAs($owner)->followingRedirects()
            ->put(route('listings.update', $listing), ['name' => 'Posted name', 'price' => 'not a number'])
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression('/<input id="name"[^>]*value="Posted name"/', $html);
        $this->assertStringNotContainsString('Stored name"', $html);
        $this->assertStringContainsString('id="price-error"', $html);
    }

    public function test_the_current_inactive_maker_and_model_are_offered_and_no_other_inactive_row(): void
    {
        $hudson = CarMaker::factory()->inactive()->create(['name' => 'Hudson']);
        $hornet = CarModel::factory()->for($hudson, 'maker')->inactive()->create(['name' => 'Hornet', 'year_from' => 1951, 'year_to' => 1957]);
        $commodore = CarModel::factory()->for($hudson, 'maker')->create(['name' => 'Commodore']);
        CarModel::factory()->for($hudson, 'maker')->inactive()->create(['name' => 'Jet']);
        CarMaker::factory()->inactive()->create(['name' => 'Packard']);
        $listing = CarListing::factory()->forCarModel($hornet)->create();

        $html = $this->page($listing)->getContent();

        $makers = $this->select($html, 'car_maker_id');
        $this->assertSame((string) $hudson->id, $this->selected($html, 'car_maker_id'));
        $this->assertStringNotContainsString('Packard', $makers);

        $models = $this->select($html, 'car_model_id');
        $this->assertMatchesRegularExpression(
            '/<optgroup label="Hudson">\s*<option value="'.$commodore->id.'">Commodore<\/option>\s*<option value="'.$hornet->id.'" selected>Hornet<\/option>\s*<\/optgroup>/',
            $models,
        );
        $this->assertStringNotContainsString('Jet', $models);

        preg_match('/<script type="application\/json" data-model-select-config>(.*?)<\/script>/s', $html, $match);
        $this->assertSame(
            [['id' => $commodore->id, 'name' => 'Commodore'], ['id' => $hornet->id, 'name' => 'Hornet']],
            json_decode($match[1], true)['models'][(string) $hudson->id],
        );
    }

    public function test_the_inactive_current_model_is_offered_only_under_its_maker_in_name_order(): void
    {
        $hudson = CarMaker::factory()->create(['name' => 'Hudson']);
        $ambassador = CarModel::factory()->for($hudson, 'maker')->inactive()->create(['name' => 'ambassador', 'year_from' => 1951, 'year_to' => 1957]);
        $commodore = CarModel::factory()->for($hudson, 'maker')->create(['name' => 'Commodore']);
        $chevrolet = CarMaker::factory()->create(['name' => 'Chevrolet']);
        $belAir = CarModel::factory()->for($chevrolet, 'maker')->create(['name' => 'Bel Air']);
        $listing = CarListing::factory()->forCarModel($ambassador)->create();

        $html = $this->page($listing)->getContent();

        $models = $this->select($html, 'car_model_id');
        $this->assertSame(1, substr_count($models, '>ambassador<'));
        $this->assertMatchesRegularExpression(
            '/<optgroup label="Hudson">\s*<option value="'.$ambassador->id.'" selected>ambassador<\/option>\s*<option value="'.$commodore->id.'">Commodore<\/option>\s*<\/optgroup>/',
            $models,
        );

        preg_match('/<script type="application\/json" data-model-select-config>(.*?)<\/script>/s', $html, $match);
        $config = json_decode($match[1], true)['models'];
        $this->assertSame([['id' => $ambassador->id, 'name' => 'ambassador'], ['id' => $commodore->id, 'name' => 'Commodore']], $config[(string) $hudson->id]);
        $this->assertSame([['id' => $belAir->id, 'name' => 'Bel Air']], $config[(string) $chevrolet->id]);
    }

    public function test_an_active_current_model_is_not_duplicated(): void
    {
        $listing = CarListing::factory()->create();

        $models = $this->select($this->page($listing)->getContent(), 'car_model_id');

        $this->assertSame(1, substr_count($models, 'value="'.$listing->car_model_id.'"'));
    }

    /**
     * @return array<string, array{string, ListingStatus, bool}>
     */
    public static function warningCases(): array
    {
        return [
            'owner, approved' => ['owner', ListingStatus::Approved, true],
            'owner, rejected' => ['owner', ListingStatus::Rejected, true],
            'owner, pending' => ['owner', ListingStatus::Pending, false],
            'admin, approved' => ['admin', ListingStatus::Approved, true],
            'admin, rejected' => ['admin', ListingStatus::Rejected, true],
            'admin, pending' => ['admin', ListingStatus::Pending, false],
        ];
    }

    #[DataProvider('warningCases')]
    public function test_the_reapproval_warning_shows_for_approved_and_rejected_listings_only(string $viewer, ListingStatus $status, bool $shown): void
    {
        $listing = $this->listing($status);
        $actor = $viewer === 'admin' ? User::factory()->admin()->create() : $listing->owner;

        $html = $this->actingAs($actor)->get(route('listings.edit', $listing))->getContent();

        $this->assertSame($shown, str_contains($html, 'Saving sends this listing for approval again'));
    }

    /**
     * @return array<string, array{int}>
     */
    public static function imageCounts(): array
    {
        return ['one image' => [1], 'eight images' => [8]];
    }

    #[DataProvider('imageCounts')]
    public function test_the_page_runs_a_fixed_number_of_queries_whatever_the_image_and_maker_count(int $count): void
    {
        CarModel::factory()->count($count)->create();
        $listing = CarListing::factory()->approved()->withImages($count)->create();
        $owner = $listing->owner;

        $this->expectsDatabaseQueryCount(5);

        $this->actingAs($owner)->get(route('listings.edit', $listing))->assertOk();
    }

    private function page(CarListing $listing): TestResponse
    {
        return $this->actingAs($listing->owner)->get(route('listings.edit', $listing))->assertOk();
    }

    private function listing(ListingStatus $status, ?User $owner = null): CarListing
    {
        $factory = match ($status) {
            ListingStatus::Approved => CarListing::factory()->approved(),
            ListingStatus::Rejected => CarListing::factory()->rejected(),
            ListingStatus::Pending => CarListing::factory(),
        };

        return $factory->for($owner ?? User::factory(), 'owner')->create();
    }

    private function select(string $html, string $name): string
    {
        preg_match('/<select id="'.$name.'".*?<\/select>/s', $html, $match);

        return preg_replace('/\s+>/', '>', $match[0]);
    }

    private function selected(string $html, string $name): ?string
    {
        preg_match_all('/<option value="([^"]*)" selected>/', $this->select($html, $name), $matches);

        $this->assertLessThanOrEqual(1, count($matches[1]), $name);

        return $matches[1][0] ?? null;
    }
}
