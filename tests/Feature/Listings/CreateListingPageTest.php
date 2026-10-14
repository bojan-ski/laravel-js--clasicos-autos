<?php

namespace Tests\Feature\Listings;

use App\Models\CarListing;
use App\Models\CarMaker;
use App\Models\CarModel;
use App\Models\User;
use App\Support\ListingFormOptions;
use BackedEnum;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CreateListingPageTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_a_guest_is_sent_to_login_on_both_routes(): void
    {
        $this->get(route('listings.create'))->assertRedirect(route('login'));
        $this->post(route('listings.store'))->assertRedirect(route('login'));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function refusedActors(): array
    {
        return [
            'admin' => ['admin'],
            'suspended app user' => ['suspended'],
        ];
    }

    #[DataProvider('refusedActors')]
    public function test_a_refused_actor_gets_the_403_page_on_both_routes_before_validation(string $actor): void
    {
        $user = $actor === 'admin' ? User::factory()->admin()->create() : User::factory()->suspended()->create();

        $this->actingAs($user)->get(route('listings.create'))->assertForbidden()->assertSee(__('app.errors.403.title'));
        $this->actingAs($user)->post(route('listings.store'))->assertForbidden()->assertSessionHasNoErrors();
    }

    public function test_an_active_app_user_sees_the_page_with_its_spanish_kicker(): void
    {
        $response = $this->page();

        $response->assertOk()->assertViewIs('pages.listings.create');
        $response->assertSee('<h1 class="text-[38px] leading-none md:text-[56px]">Create Car Listing</h1>', false);
        $this->assertMatchesRegularExpression('/<[^>]+lang="es"[^>]*>\s*Vende tu clásico/u', $response->getContent());
    }

    public function test_the_six_sections_render_in_order_with_rail_anchors_and_six_progress_segments(): void
    {
        $html = $this->page()->getContent();

        preg_match_all('/<fieldset id="([^"]+)" data-form-section/', $html, $sections);
        $this->assertSame(['section-car', 'section-engine', 'section-history', 'section-looks', 'section-location', 'section-description'], $sections[1]);

        preg_match_all('/<a href="#(section-[a-z]+)"/', $html, $anchors);
        $this->assertSame($sections[1], $anchors[1]);

        $this->assertSeeInOrderHtml($html, ['I.', 'The car', 'II.', 'Engine', 'III.', 'History &amp; papers', 'IV.', 'Looks', 'V.', 'Location', 'VI.', 'Description &amp; photos']);
        $this->assertSame(6, substr_count($html, 'data-form-progress-segment'));
        $this->assertStringContainsString('data-form-progress aria-hidden="true"', $html);
        $this->assertStringContainsString('sticky top-0 z-10', $html);
    }

    public function test_every_field_carries_its_verbatim_label_and_placeholder(): void
    {
        $html = $this->page()->getContent();

        foreach ($this->fieldTexts() as $name => [$label, $placeholder]) {
            $this->assertMatchesRegularExpression('/<label for="'.$name.'"[^>]*>'.preg_quote(e($label), '/').'/', $html, $name);
            $this->assertTrue(
                str_contains($html, 'placeholder="'.e($placeholder).'"') || str_contains($html, '<option value="">'.e($placeholder).'</option>'),
                "Placeholder of {$name}",
            );
        }
    }

    public function test_required_fields_show_the_marker_and_the_other_model_field_is_not_required(): void
    {
        $html = $this->page()->getContent();

        $this->assertStringContainsString('* Required', $html);
        $this->assertMatchesRegularExpression('/<label for="name"[^>]*>Name <span aria-hidden="true">\*<\/span>/', $html);
        $this->assertMatchesRegularExpression('/<label for="engine_size"[^>]*>Engine Size<\/label>/', $html);
        $this->assertMatchesRegularExpression('/<label for="model"[^>]*>Other model<\/label>/', $html);
        $this->assertDoesNotMatchRegularExpression('/<input id="model"[^>]*required/', $html);
        $this->assertMatchesRegularExpression('/<input id="model"[^>]*aria-describedby="model-hint"/', $html);
        $this->assertStringContainsString('<p id="model-hint" class="text-xs text-muted">Required when Other (specify) is chosen</p>', $html);
        $this->assertStringContainsString('<span class="sr-only">(required)</span>', $html);
        $this->assertMatchesRegularExpression('/<textarea id="description"[^>]*maxlength="5000"/', $html);
    }

    public function test_every_enum_select_lists_its_cases_in_order_after_the_placeholder(): void
    {
        $html = $this->page()->getContent();

        foreach (ListingFormOptions::ENUM_FIELDS as $field => $enum) {
            $expected = array_map(fn (BackedEnum $case): string => (string) $case->value, $enum::cases());

            $this->assertSame(['', ...$expected], $this->optionValues($this->select($html, $field)), $field);
        }
    }

    public function test_the_maker_select_lists_only_active_makers_in_name_order(): void
    {
        $cadillac = CarMaker::factory()->create(['name' => 'Cadillac']);
        $buick = CarMaker::factory()->create(['name' => 'Buick']);
        CarMaker::factory()->inactive()->create(['name' => 'Hudson']);

        $select = $this->select($this->page()->getContent(), 'car_maker_id');

        $this->assertSame(['', (string) $buick->id, (string) $cadillac->id], $this->optionValues($select));
        $this->assertStringNotContainsString('Hudson', $select);
    }

    public function test_the_model_select_groups_active_models_by_active_maker_and_ends_with_other(): void
    {
        $chevrolet = CarMaker::factory()->create(['name' => 'Chevrolet']);
        $buick = CarMaker::factory()->create(['name' => 'Buick']);
        CarMaker::factory()->create(['name' => 'Cord']);
        $hudson = CarMaker::factory()->inactive()->create(['name' => 'Hudson']);
        $impala = CarModel::factory()->for($chevrolet, 'maker')->create(['name' => 'Impala']);
        $belAir = CarModel::factory()->for($chevrolet, 'maker')->create(['name' => 'Bel Air']);
        $roadmaster = CarModel::factory()->for($buick, 'maker')->create(['name' => 'Roadmaster']);
        CarModel::factory()->for($chevrolet, 'maker')->inactive()->create(['name' => 'Nomad']);
        CarModel::factory()->for($hudson, 'maker')->create(['name' => 'Hornet']);

        $select = $this->select($this->page()->getContent(), 'car_model_id');

        $this->assertMatchesRegularExpression(
            '/<option value="">Select car model<\/option>\s*'
            .'<optgroup label="Buick">\s*<option value="'.$roadmaster->id.'">Roadmaster<\/option>\s*<\/optgroup>\s*'
            .'<optgroup label="Chevrolet">\s*<option value="'.$belAir->id.'">Bel Air<\/option>\s*<option value="'.$impala->id.'">Impala<\/option>\s*<\/optgroup>\s*'
            .'<option value="other">Other \(specify\)<\/option>\s*<\/select>/',
            $select,
        );
        $this->assertStringNotContainsString('Cord', $select);
        $this->assertStringNotContainsString('Nomad', $select);
        $this->assertStringNotContainsString('Hornet', $select);
    }

    public function test_the_model_config_holds_active_models_per_active_maker(): void
    {
        $chevrolet = CarMaker::factory()->create(['name' => 'Chevrolet']);
        $cord = CarMaker::factory()->create(['name' => 'Cord']);
        $hudson = CarMaker::factory()->inactive()->create(['name' => 'Hudson']);
        $belAir = CarModel::factory()->for($chevrolet, 'maker')->create(['name' => 'Bel Air']);
        CarModel::factory()->for($chevrolet, 'maker')->inactive()->create(['name' => 'Nomad']);
        CarModel::factory()->for($hudson, 'maker')->create(['name' => 'Hornet']);

        preg_match('/<script type="application\/json" data-model-select-config>(.*?)<\/script>/s', $this->page()->getContent(), $match);

        $this->assertSame([
            'other' => 'other',
            'models' => [
                (string) $chevrolet->id => [['id' => $belAir->id, 'name' => 'Bel Air']],
                (string) $cord->id => [],
            ],
        ], json_decode($match[1], true));
    }

    public function test_the_member_header_offers_list_your_car_and_no_nav_link_is_current(): void
    {
        $html = $this->page()->getContent();

        $this->assertStringContainsString('href="'.route('listings.create').'"', $html);
        $this->assertStringContainsString('List your car', $html);
        $this->assertStringNotContainsString('aria-current="page"', $html);
    }

    public function test_the_listings_link_stays_current_on_index_advanced_and_detail(): void
    {
        $user = User::factory()->create();
        $listing = CarListing::factory()->approved()->create();
        $currentListingsLink = '/<a href="'.preg_quote(route('listings.index'), '/').'"\s+aria-current="page"/';

        foreach ([route('listings.index'), route('listings.advanced'), route('listings.show', $listing)] as $url) {
            $this->assertMatchesRegularExpression($currentListingsLink, $this->actingAs($user)->get($url)->getContent(), $url);
        }
    }

    public function test_the_actions_row_and_the_mobile_sticky_bar_render(): void
    {
        $html = $this->page()->getContent();

        preg_match('/<div data-sticky-bar class="([^"]*)"/', $html, $bar);
        $this->assertStringStartsWith('sticky bottom-(--cookie-banner-h,0px) z-10 ', $bar[1]);
        $this->assertStringContainsString('md:hidden', $bar[1]);
        $this->assertStringNotContainsString('bottom-0', $bar[1]);
        $this->assertSame(2, substr_count($html, 'Add Car Listing'));
        $this->assertMatchesRegularExpression('/<a href="'.preg_quote(route('profile'), '/').'"[^>]*>\s*Cancel\s*<\/a>/', $html);
        $this->assertStringContainsString('enctype="multipart/form-data"', $html);
    }

    public function test_the_three_photo_inputs_post_images_without_the_required_attribute(): void
    {
        $html = $this->page()->getContent();

        preg_match_all('/<input id="images-[a-z]+"[^>]*>/', $html, $inputs);
        $this->assertCount(3, $inputs[0]);

        foreach ($inputs[0] as $input) {
            $this->assertStringContainsString('name="images[]"', $input);
            $this->assertStringContainsString('accept="image/jpeg,image/png"', $input);
            $this->assertStringNotContainsString('required', $input);
        }

        [$desktop, $camera, $library] = $inputs[0];
        $this->assertStringContainsString('multiple', $desktop);
        $this->assertStringContainsString('capture="environment"', $camera);
        $this->assertStringNotContainsString('multiple', $camera);
        $this->assertStringContainsString('multiple', $library);
        $this->assertStringContainsString('Car images - max 8 <span aria-hidden="true">*</span>', $html);
        $this->assertStringContainsString('data-count-one=":count photo selected"', $html);
        $this->assertStringNotContainsString(__('app.listings.form.photos.reselect'), $html);
    }

    private function page(): TestResponse
    {
        return $this->actingAs(User::factory()->create())->get(route('listings.create'));
    }

    private function select(string $html, string $name): string
    {
        preg_match('/<select id="'.$name.'".*?<\/select>/s', $html, $match);

        return preg_replace('/\s+>/', '>', $match[0]);
    }

    /**
     * @return list<string>
     */
    private function optionValues(string $select): array
    {
        preg_match_all('/<option value="([^"]*)"/', $select, $matches);

        return $matches[1];
    }

    /**
     * @param  list<string>  $texts
     */
    private function assertSeeInOrderHtml(string $html, array $texts): void
    {
        $position = 0;

        foreach ($texts as $text) {
            $found = strpos($html, $text, $position);
            $this->assertNotFalse($found, $text);
            $position = $found + strlen($text);
        }
    }

    /**
     * @return array<string, array{string, string}>
     */
    private function fieldTexts(): array
    {
        return [
            'name' => ['Name', 'Enter the car name'],
            'car_maker_id' => ['Car Maker', 'Select car maker'],
            'car_model_id' => ['Model', 'Select car model'],
            'model' => ['Other model', 'Enter the car model'],
            'price' => ['Price', 'Enter the price'],
            'year' => ['Year', 'Enter year'],
            'mileage' => ['Mileage', 'Enter the mileage'],
            'transmission' => ['Transmission', 'Select the transmission'],
            'fuel_type' => ['Fuel type', 'Select fuel type'],
            'engine_size' => ['Engine Size', 'Enter the engine size'],
            'engine_type' => ['Engine Type', 'Enter the engine type'],
            'engine_history' => ['Engine History', 'Select the engine history'],
            'engine_condition' => ['Engine Condition', 'Select the engine condition'],
            'restoration_history' => ['Restoration History', 'Select the restoration history'],
            'original_parts_percentage' => ['Original parts percentage', 'Original parts percentage (%)'],
            'odometer' => ['Odometer', 'Select odometer'],
            'license_plate_type' => ['License Plate Type', 'Select the license plate type'],
            'documentation_status' => ['Documentation Status', 'Select the documentation status'],
            'body_type' => ['Car Body Type', 'Select the car body type'],
            'exterior_color' => ['Exterior Color', 'Enter the exterior color'],
            'exterior_condition' => ['Exterior Condition', 'Select the exterior condition'],
            'interior_color' => ['Interior Color', 'Enter the interior color'],
            'interior_condition' => ['Interior Condition', 'Select the interior condition'],
            'seat_material' => ['Seat Material', 'Select the seat material'],
            'location_city' => ['City', 'Enter the city name'],
            'location_state' => ['State', 'Enter the state name'],
            'location_zipcode' => ['Zipcode', 'Enter the zipcode'],
            'description' => ['Car description', 'Enter car description'],
        ];
    }
}
