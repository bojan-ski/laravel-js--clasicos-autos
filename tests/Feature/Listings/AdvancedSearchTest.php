<?php

namespace Tests\Feature\Listings;

use App\Models\CarListing;
use App\Models\CarMaker;
use App\Models\CarModel;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdvancedSearchTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    /**
     * @return array<string, array{string}>
     */
    public static function viewers(): array
    {
        return [
            'guest' => ['guest'],
            'app user' => ['app_user'],
            'suspended app user' => ['suspended'],
            'admin' => ['admin'],
        ];
    }

    #[DataProvider('viewers')]
    public function test_the_page_is_open_to_every_viewer(string $viewer): void
    {
        $this->actAs($viewer);

        $this->get(route('listings.advanced'))
            ->assertOk()
            ->assertViewIs('pages.listings.advanced');
    }

    public function test_the_route_resolves_to_the_advanced_action(): void
    {
        $this->assertSame('App\Http\Controllers\Listings\ListingController@advanced', app('router')->getRoutes()->getByName('listings.advanced')->getActionName());
    }

    public function test_the_listings_nav_item_is_current_on_the_page(): void
    {
        $this->assertMatchesRegularExpression(
            '/<a href="'.preg_quote(route('listings.index'), '/').'"\s+aria-current="page"/',
            $this->get(route('listings.advanced'))->getContent(),
        );
    }

    /**
     * @return array<string, array{string}>
     */
    public static function visibilityViewers(): array
    {
        return [
            'guest' => ['guest'],
            'owner of the hidden listings' => ['owner'],
            'admin' => ['admin'],
        ];
    }

    #[DataProvider('visibilityViewers')]
    public function test_only_public_listings_match_for_every_viewer(string $viewer): void
    {
        $owner = User::factory()->create();
        $this->approved(['name' => 'Visible Bel Air', 'model' => 'Bel Air']);
        CarListing::factory()->for($owner, 'owner')->customModel()->create(['name' => 'Pending Bel Air', 'model' => 'Bel Air']);
        CarListing::factory()->for($owner, 'owner')->customModel()->rejected()->create(['name' => 'Rejected Bel Air', 'model' => 'Bel Air']);
        CarListing::factory()->for(User::factory()->suspended(), 'owner')->customModel()->approved()->create(['name' => 'Suspended Bel Air', 'model' => 'Bel Air']);

        if ($viewer === 'owner') {
            $this->actingAs($owner);
        } else {
            $this->actAs($viewer);
        }

        $this->assertSame(['Visible Bel Air'], $this->namesFor(['model' => 'Bel Air']));
        $this->assertSame(['Visible Bel Air'], $this->namesFor([]));
        $this->assertSame(1, $this->get(route('listings.advanced'))->viewData('listings')->total());
    }

    public function test_hidden_listings_take_no_page_slots_and_stay_out_of_the_total(): void
    {
        foreach (range(1, 8) as $age) {
            $this->approved(['name' => "Public {$age}", 'created_at' => now()->subHours($age)]);
        }

        CarListing::factory()->count(3)->customModel()->create(['name' => 'Newer Pending']);
        CarListing::factory()->for(User::factory()->suspended(), 'owner')->customModel()->approved()->create(['name' => 'Newer Suspended']);

        $response = $this->get(route('listings.advanced'));

        $this->assertSame(8, $response->viewData('listings')->total());
        $this->assertSame(array_map(fn (int $age): string => "Public {$age}", range(1, 8)), $response->viewData('listings')->pluck('name')->all());
        $response->assertSee(trans_choice('app.listings.advanced.show', 8, ['count' => 8]));
    }

    public function test_the_bare_page_lists_every_public_listing_newest_first_with_the_id_breaking_ties(): void
    {
        $this->approved(['name' => 'Oldest Car', 'created_at' => now()->subHours(2)]);
        $this->approved(['name' => 'Tied Earlier Id', 'created_at' => now()->subHour()]);
        $this->approved(['name' => 'Tied Later Id', 'created_at' => now()->subHour()]);

        $this->assertSame(['Tied Later Id', 'Tied Earlier Id', 'Oldest Car'], $this->namesFor([]));
    }

    public function test_eight_listings_per_page_and_page_two_holds_the_ninth(): void
    {
        foreach (range(1, 9) as $age) {
            $this->approved(['name' => "Listing Age {$age}", 'created_at' => now()->subMinutes($age)]);
        }

        $this->assertCount(8, $this->namesFor([]));
        $this->assertNotContains('Listing Age 9', $this->namesFor([]));
        $this->assertSame(['Listing Age 9'], $this->namesFor(['page' => 2]));
    }

    public function test_the_maker_filter_keeps_only_that_makers_listings(): void
    {
        $maker = CarMaker::factory()->create();
        $this->approved(['name' => 'Maker Car'], CarModel::factory()->for($maker, 'maker')->create());
        $this->approved(['name' => 'Other Car']);

        $this->assertSame(['Maker Car'], $this->namesFor(['car_maker' => $maker->id]));
    }

    /**
     * @return array<string, array{string, string, array<string, string>, array<string, string>}>
     */
    public static function optionFilters(): array
    {
        return [
            'transmission' => ['transmission', 'Manual', ['transmission' => 'Manual'], ['transmission' => 'Automatic']],
            'fuel type' => ['fuel_type', 'Diesel', ['fuel_type' => 'Diesel'], ['fuel_type' => 'Gasoline']],
            'body type' => ['body_type', 'Coupe', ['body_type' => 'Coupe'], ['body_type' => 'Sedan']],
            'engine history' => ['engine_history', 'Rebuilt Engine', ['engine_history' => 'Rebuilt Engine'], ['engine_history' => 'Original Engine']],
            'engine condition' => ['engine_condition', 'Not Running', ['engine_condition' => 'Not Running'], ['engine_condition' => 'Running']],
            'restoration history' => ['restoration_history', 'Barn Find', ['restoration_history' => 'Barn Find'], ['restoration_history' => 'Fully Restored']],
            'odometer' => ['odometer', 'Rebuilt', ['odometer' => 'Rebuilt'], ['odometer' => 'Original']],
            'license plate type' => ['license_plate_type', 'No Plate', ['license_plate_type' => 'No Plate'], ['license_plate_type' => 'Modern Plate']],
            'documentation status' => ['documentation_status', 'Missing Papers', ['documentation_status' => 'Missing Papers'], ['documentation_status' => 'Original Papers']],
            'exterior condition' => ['exterior_condition', 'Good', ['exterior_condition' => 'Good', 'interior_condition' => 'Fair'], ['exterior_condition' => 'Fair', 'interior_condition' => 'Good']],
            'interior condition' => ['interior_condition', 'Good', ['interior_condition' => 'Good', 'exterior_condition' => 'Fair'], ['interior_condition' => 'Fair', 'exterior_condition' => 'Good']],
            'seat material' => ['seat_material', 'Velour', ['seat_material' => 'Velour'], ['seat_material' => 'Leather']],
        ];
    }

    /**
     * @param  array<string, string>  $matching
     * @param  array<string, string>  $other
     */
    #[DataProvider('optionFilters')]
    public function test_each_option_select_keeps_only_exact_matches(string $field, string $value, array $matching, array $other): void
    {
        $this->approved(['name' => 'Matching Car', ...$matching]);
        $this->approved(['name' => 'Other Car', ...$other]);

        $this->assertSame(['Matching Car'], $this->namesFor([$field => $value]));
    }

    public function test_engine_size_and_engine_type_match_the_whole_value_ignoring_case(): void
    {
        $this->approved(['name' => 'Exact Engine', 'engine_size' => '4.6L', 'engine_type' => 'V8']);
        $this->approved(['name' => 'Longer Engine', 'engine_size' => '4.6L Turbo', 'engine_type' => 'V8 Hemi']);

        $this->assertSame(['Exact Engine'], $this->namesFor(['engine_size' => '  4.6l ']));
        $this->assertSame(['Exact Engine'], $this->namesFor(['engine_type' => 'v8']));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function substringFilters(): array
    {
        return [
            'model' => ['model', 'model'],
            'exterior color' => ['exterior_color', 'exterior_color'],
            'interior color' => ['interior_color', 'interior_color'],
            'city' => ['city', 'location_city'],
            'state' => ['state', 'location_state'],
            'zipcode' => ['zipcode', 'location_zipcode'],
        ];
    }

    #[DataProvider('substringFilters')]
    public function test_each_text_filter_matches_a_substring_ignoring_case_with_a_literal_underscore(string $field, string $column): void
    {
        $this->approved(['name' => 'Underscore Car', $column => 'Ab_Cd']);
        $this->approved(['name' => 'Wildcard Car', $column => 'AbXCd']);

        $this->assertSame(['Underscore Car'], $this->namesFor([$field => 'b_c']));
        $this->assertSame(['Wildcard Car', 'Underscore Car'], $this->namesFor([$field => 'AB']));
    }

    public function test_a_percent_sign_in_a_text_filter_matches_literally(): void
    {
        $this->approved(['name' => 'Percent Car', 'model' => '100% Special']);
        $this->approved(['name' => 'Digits Car', 'model' => '1000 Special']);

        $this->assertSame(['Percent Car'], $this->namesFor(['model' => '100%']));
    }

    /**
     * @return array<string, array{string, string, list<string>}>
     */
    public static function boundFilters(): array
    {
        return [
            'year from' => ['year_from', 'year', ['Value 1961', 'Value 1960']],
            'year to' => ['year_to', 'year', ['Value 1960', 'Value 1959']],
            'price from' => ['price_from', 'price', ['Value 1961', 'Value 1960']],
            'price to' => ['price_to', 'price', ['Value 1960', 'Value 1959']],
            'mileage' => ['mileage', 'mileage', ['Value 1960', 'Value 1959']],
        ];
    }

    /**
     * @param  list<string>  $expected
     */
    #[DataProvider('boundFilters')]
    public function test_each_bound_is_inclusive_and_works_alone(string $field, string $column, array $expected): void
    {
        foreach ([1959, 1960, 1961] as $index => $value) {
            $this->approved(['name' => "Value {$value}", $column => $value, 'created_at' => now()->subMinutes(10 - $index)]);
        }

        $this->assertSame($expected, $this->namesFor([$field => 1960]));
    }

    public function test_a_range_includes_both_ends_and_a_reversed_range_matches_nothing(): void
    {
        foreach ([1959, 1960, 1961, 1962] as $index => $year) {
            $this->approved(['name' => "Year {$year}", 'year' => $year, 'price' => $year * 10, 'created_at' => now()->subMinutes(10 - $index)]);
        }

        $this->assertSame(['Year 1961', 'Year 1960'], $this->namesFor(['year_from' => 1960, 'year_to' => 1961]));
        $this->assertSame(['Year 1961', 'Year 1960'], $this->namesFor(['price_from' => 19600, 'price_to' => 19610]));
        $this->assertSame([], $this->namesFor(['year_from' => 1961, 'year_to' => 1960]));
        $this->get(route('listings.advanced', ['price_from' => 19610, 'price_to' => 19600]))->assertOk();
    }

    public function test_mileage_zero_is_a_value_not_an_empty_filter(): void
    {
        $this->approved(['name' => 'Zero Miles', 'mileage' => 0]);
        $this->approved(['name' => 'Some Miles', 'mileage' => 10]);

        $this->assertSame(['Zero Miles'], $this->namesFor(['mileage' => 0]));
    }

    public function test_filters_combine(): void
    {
        $this->approved(['name' => 'All Match', 'model' => 'Bel Air', 'transmission' => 'Manual', 'price' => 20000]);
        $this->approved(['name' => 'Wrong Transmission', 'model' => 'Bel Air', 'transmission' => 'Automatic', 'price' => 20000]);
        $this->approved(['name' => 'Too Expensive', 'model' => 'Bel Air', 'transmission' => 'Manual', 'price' => 90000]);
        $this->approved(['name' => 'Wrong Model', 'model' => 'Impala', 'transmission' => 'Manual', 'price' => 20000]);

        $this->assertSame(['All Match'], $this->namesFor(['model' => 'bel', 'transmission' => 'Manual', 'price_to' => 50000]));
    }

    public function test_empty_and_blank_values_add_no_condition(): void
    {
        $this->approved(['name' => 'First Car']);
        $this->approved(['name' => 'Second Car']);

        $response = $this->get(route('listings.advanced', ['model' => '   ', 'car_maker' => '', 'transmission' => '', 'year_from' => '']));

        $this->assertCount(2, $response->viewData('listings'));
        $this->assertFalse($response->viewData('filtered'));
    }

    public function test_pagination_links_keep_the_submitted_parameters(): void
    {
        foreach (range(1, 9) as $index) {
            $this->approved(['name' => "Match {$index}", 'model' => 'Bel Air', 'transmission' => 'Manual']);
        }

        $this->get(route('listings.advanced', ['model' => 'Bel', 'transmission' => 'Manual']))
            ->assertSee(e(route('listings.advanced', ['model' => 'Bel', 'transmission' => 'Manual', 'page' => 2])), false);
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function invalidQueries(): array
    {
        return [
            'year before the minimum' => [['year_from' => 1899], 'year_from'],
            'year after the cutoff' => [['year_to' => 1990], 'year_to'],
            'non-integer price' => [['price_from' => '12.5'], 'price_from'],
            'negative mileage' => [['mileage' => -1], 'mileage'],
            'unknown option' => [['transmission' => 'Hover'], 'transmission'],
            'unknown maker' => [['car_maker' => 999999], 'car_maker'],
            'year_from after the cutoff' => [['year_from' => 1990], 'year_from'],
            'year_to before the minimum' => [['year_to' => 1899], 'year_to'],
            'negative price_from' => [['price_from' => -1], 'price_from'],
            'negative price_to' => [['price_to' => -1], 'price_to'],
            'model over 255 characters' => [['model' => str_repeat('a', 256)], 'model'],
            'engine_size over 50 characters' => [['engine_size' => str_repeat('a', 51)], 'engine_size'],
            'engine_type over 100 characters' => [['engine_type' => str_repeat('a', 101)], 'engine_type'],
            'exterior_color over 50 characters' => [['exterior_color' => str_repeat('a', 51)], 'exterior_color'],
            'interior_color over 50 characters' => [['interior_color' => str_repeat('a', 51)], 'interior_color'],
            'city over 100 characters' => [['city' => str_repeat('a', 101)], 'city'],
            'state over 100 characters' => [['state' => str_repeat('a', 101)], 'state'],
            'unknown body type' => [['body_type' => 'Hovercraft'], 'body_type'],
            'zipcode over 20 characters' => [['zipcode' => str_repeat('1', 21)], 'zipcode'],
            'array text value' => [['model' => ['Bel Air']], 'model'],
            'array option value' => [['transmission' => ['Manual']], 'transmission'],
            'array maker value' => [['car_maker' => [1]], 'car_maker'],
            'array number value' => [['year_from' => [1950]], 'year_from'],
        ];
    }

    /**
     * @param  array<string, mixed>  $query
     */
    #[DataProvider('invalidQueries')]
    public function test_invalid_input_redirects_to_the_bare_page_with_the_error(array $query, string $field): void
    {
        $this->get(route('listings.advanced', $query))
            ->assertRedirect(route('listings.advanced'))
            ->assertSessionHasErrors($field);
    }

    public function test_values_on_every_boundary_are_accepted(): void
    {
        $this->get(route('listings.advanced', [
            'year_from' => 1900,
            'year_to' => 1989,
            'price_from' => 0,
            'model' => str_repeat('a', 255),
            'engine_size' => str_repeat('a', 50),
            'engine_type' => str_repeat('a', 100),
            'city' => str_repeat('a', 100),
            'zipcode' => str_repeat('1', 20),
        ]))->assertOk()->assertSessionHasNoErrors();
    }

    public function test_a_zero_value_counts_as_an_active_filter(): void
    {
        $this->assertTrue($this->get(route('listings.advanced', ['mileage' => 0]))->viewData('filtered'));
        $this->assertFalse($this->get(route('listings.advanced'))->viewData('filtered'));
    }

    public function test_an_inactive_maker_is_invalid_and_not_offered(): void
    {
        $inactive = CarMaker::factory()->inactive()->create(['name' => 'Inactive Motors']);
        CarMaker::factory()->create(['name' => 'Active Motors']);

        $this->get(route('listings.advanced', ['car_maker' => $inactive->id]))
            ->assertRedirect(route('listings.advanced'))
            ->assertSessionHasErrors('car_maker');

        $this->get(route('listings.advanced'))
            ->assertSee('Active Motors')
            ->assertDontSee('Inactive Motors');
    }

    public function test_listings_of_an_inactive_maker_still_match_without_a_maker_filter(): void
    {
        $inactive = CarMaker::factory()->inactive()->create();
        $this->approved(['name' => 'Discontinued Coupe', 'model' => 'Bel Air'], CarModel::factory()->for($inactive, 'maker')->create());

        $this->assertSame(['Discontinued Coupe'], $this->namesFor([]));
        $this->assertSame(['Discontinued Coupe'], $this->namesFor(['model' => 'bel']));
    }

    public function test_the_refilled_page_shows_the_alert_the_inline_error_and_the_value(): void
    {
        $this->approved(['name' => 'Some Car']);

        $html = $this->followingRedirects()
            ->get(route('listings.advanced', ['year_from' => 1899, 'model' => 'Bel']))
            ->assertOk()
            ->getContent();

        $message = e(__('validation.between.numeric', ['attribute' => 'Year From', 'min' => 1900, 'max' => 1989]));

        $this->assertMatchesRegularExpression('/role="alert"[\s\S]*'.preg_quote($message, '/').'/', $html);
        $this->assertStringContainsString('<p id="year_from-error" class="text-xs text-red-deep">'.$message.'</p>', $html);
        $this->assertMatchesRegularExpression('/<input id="year_from" name="year_from" type="number"\s+value="1899"[^>]*aria-invalid="true"/', $html);
        $this->assertMatchesRegularExpression('/<input id="model" name="model" type="text"\s+value="Bel"/', $html);
        $this->assertStringContainsString('Some Car', $html);
    }

    public function test_an_array_value_never_reaches_the_markup(): void
    {
        $html = $this->followingRedirects()
            ->get(route('listings.advanced', ['model' => ['Bel Air'], 'transmission' => ['Manual']]))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('Array', $html);
        $this->assertStringContainsString('aria-describedby="model-error"', $html);
        $this->assertStringContainsString('aria-describedby="transmission-error"', $html);
    }

    public function test_controls_show_the_normalized_submitted_values(): void
    {
        $maker = CarMaker::factory()->create();

        $html = $this->get(route('listings.advanced', [
            'model' => '  Bel Air  ',
            'year_from' => '1950',
            'transmission' => 'Manual',
            'car_maker' => $maker->id,
        ]))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<input id="model"[^>]*value="Bel Air"/', $html);
        $this->assertMatchesRegularExpression('/<input id="year_from"[^>]*value="1950"/', $html);
        $this->assertMatchesRegularExpression('/<option value="Manual"\s+selected>/', $html);
        $this->assertMatchesRegularExpression('/<option value="'.$maker->id.'"\s+selected>/', $html);
        $this->assertSame(2, preg_match_all('/\s+selected>/', $html));
    }

    public function test_each_select_offers_a_selectable_empty_default_option(): void
    {
        $html = $this->get(route('listings.advanced'))->getContent();

        $this->assertMatchesRegularExpression('/<select id="car_maker"[^>]*>\s*<option value="">'.preg_quote(__('app.listings.advanced.fields.car_maker.placeholder'), '/').'<\/option>/', $html);
        $this->assertSame(12 + 1, substr_count($html, '<option value="">'));
        $this->assertDoesNotMatchRegularExpression('/<option[^>]*disabled/', $html);
    }

    public function test_the_count_line_always_renders_including_zero(): void
    {
        $this->get(route('listings.advanced'))
            ->assertSee(__('app.listings.advanced.results'))
            ->assertSee(trans_choice('app.listings.index.count', 0, ['count' => 0]));

        $this->approved(['name' => 'Only Car', 'model' => 'Bel Air']);

        $this->get(route('listings.advanced', ['model' => 'Bel']))
            ->assertSee(trans_choice('app.listings.index.count', 1, ['count' => 1]));
    }

    public function test_the_unfiltered_empty_state_renders_without_listings(): void
    {
        $this->get(route('listings.advanced'))
            ->assertOk()
            ->assertSee(__('app.listings.index.empty'))
            ->assertDontSee(__('app.listings.index.reset'));
    }

    public function test_a_filter_without_matches_shows_the_filtered_empty_state(): void
    {
        $this->approved(['name' => 'Bel Air', 'transmission' => 'Manual']);

        $this->get(route('listings.advanced', ['transmission' => 'Automatic']))
            ->assertOk()
            ->assertSee(__('app.listings.index.no_results'))
            ->assertSee('href="'.route('listings.index').'"', false)
            ->assertSee(__('app.listings.index.reset'))
            ->assertDontSee(__('app.listings.index.empty'));
    }

    public function test_a_page_beyond_the_last_renders_the_empty_state(): void
    {
        $this->approved(['name' => 'Bel Air']);

        $this->get(route('listings.advanced', ['page' => 9]))
            ->assertOk()
            ->assertSee(__('app.listings.index.empty'));
    }

    public function test_clear_links_to_the_bare_page_and_back_links_to_the_index(): void
    {
        $html = $this->get(route('listings.advanced', ['model' => 'Bel']))->getContent();

        $this->assertMatchesRegularExpression('/<a href="'.preg_quote(route('listings.advanced'), '/').'"[^>]*>\s*'.__('app.listings.advanced.clear').'\s*<\/a>/', $html);
        $this->assertMatchesRegularExpression('/<a href="'.preg_quote(route('listings.index'), '/').'"[^>]*>(?:(?!<\/a>)[\s\S])*'.__('app.listings.advanced.back').'\s*<\/a>/', $html);
    }

    public function test_the_form_has_five_fieldsets_with_the_fields_in_order(): void
    {
        $html = $this->get(route('listings.advanced'))->getContent();

        $this->assertSame(5, substr_count($html, '<fieldset'));
        $this->assertMatchesRegularExpression('/<form method="GET" action="'.preg_quote(route('listings.advanced'), '/').'"/', $html);

        $order = [];

        foreach ([
            'car' => ['car_maker', 'model', 'year_from', 'year_to', 'mileage', 'transmission', 'fuel_type', 'body_type'],
            'engine' => ['engine_size', 'engine_type', 'engine_history', 'engine_condition'],
            'history' => ['restoration_history', 'odometer', 'license_plate_type', 'documentation_status'],
            'looks' => ['exterior_color', 'exterior_condition', 'interior_color', 'interior_condition', 'seat_material'],
            'location' => ['city', 'state', 'zipcode', 'price_from', 'price_to'],
        ] as $fieldset => $fields) {
            $order[] = e(__("app.listings.advanced.fieldsets.{$fieldset}.title"));
            $order[] = e(__("app.listings.advanced.fieldsets.{$fieldset}.note"));

            foreach ($fields as $field) {
                $order[] = 'id="'.$field.'"';
            }
        }

        $this->assertCount(26 + 10, $order);
        $this->get(route('listings.advanced'))->assertSeeInOrder($order, false);
    }

    public function test_number_and_text_inputs_carry_their_bounds(): void
    {
        $html = $this->get(route('listings.advanced'))->getContent();

        $this->assertMatchesRegularExpression('/id="year_from"[^>]*min="1900"[^>]*max="1989"/', $html);
        $this->assertMatchesRegularExpression('/id="year_to"[^>]*min="1900"[^>]*max="1989"/', $html);
        $this->assertMatchesRegularExpression('/id="mileage" name="mileage" type="number"[^>]*min="0"/', $html);
        $this->assertMatchesRegularExpression('/id="price_to" name="price_to" type="number"[^>]*min="0"/', $html);
        $this->assertMatchesRegularExpression('/id="model" name="model" type="text"[^>]*maxlength="255"/', $html);
        $this->assertMatchesRegularExpression('/id="zipcode" name="zipcode" type="text"[^>]*maxlength="20"[^>]*inputmode="numeric"/', $html);
    }

    public function test_the_mobile_bar_submits_with_the_current_total_and_desktop_has_search(): void
    {
        $this->approved(['name' => 'First Car']);
        $this->approved(['name' => 'Second Car']);

        $html = $this->get(route('listings.advanced'))->getContent();

        $this->assertMatchesRegularExpression('/data-sticky-bar[^>]*>\s*<button type="submit"[^>]*>'.trans_choice('app.listings.advanced.show', 2, ['count' => 2]).'<\/button>/', $html);
        $this->assertMatchesRegularExpression('/<button type="submit"[^>]*>'.__('app.listings.advanced.search').'<\/button>/', $html);
        $this->assertSame('Show 1 listing', trans_choice('app.listings.advanced.show', 1, ['count' => 1]));
    }

    public function test_the_spanish_kicker_is_marked_as_spanish(): void
    {
        $this->assertMatchesRegularExpression(
            '/lang="es"[^>]*>'.preg_quote(__('app.listings.advanced.kicker'), '/').'</',
            $this->get(route('listings.advanced'))->getContent(),
        );
    }

    public function test_the_query_count_does_not_grow_with_the_listings_on_the_page_and_nothing_is_written(): void
    {
        CarListing::factory()->approved()->withImages()->create();
        $one = $this->queriesFor(route('listings.advanced'));

        CarListing::factory()->count(7)->approved()->withImages()->create();
        $eight = $this->queriesFor(route('listings.advanced'));

        $this->assertSame(count($one), count($eight));
        $this->assertSame([], array_filter($eight, fn (string $sql): bool => ! str_starts_with(strtolower(ltrim($sql)), 'select')));
    }

    private function actAs(string $viewer): void
    {
        match ($viewer) {
            'guest' => null,
            'app_user' => $this->actingAs(User::factory()->create()),
            'suspended' => $this->actingAs(User::factory()->suspended()->create()),
            'admin' => $this->actingAs(User::factory()->admin()->create()),
        };
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function approved(array $attributes = [], ?CarModel $carModel = null): CarListing
    {
        $factory = CarListing::factory()->approved();

        $factory = $carModel !== null ? $factory->forCarModel($carModel) : $factory->customModel();

        return $factory->create($attributes);
    }

    /**
     * @param  array<string, mixed>  $query
     * @return list<string>
     */
    private function namesFor(array $query): array
    {
        return $this->get(route('listings.advanced', $query))
            ->assertOk()
            ->viewData('listings')
            ->pluck('name')
            ->all();
    }

    /**
     * @return list<string>
     */
    private function queriesFor(string $url): array
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->get($url)->assertOk();

        $queries = array_column(DB::getQueryLog(), 'query');
        DB::disableQueryLog();

        return $queries;
    }
}
