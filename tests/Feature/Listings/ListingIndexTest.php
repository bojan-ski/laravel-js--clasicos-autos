<?php

namespace Tests\Feature\Listings;

use App\Models\CarListing;
use App\Models\CarMaker;
use App\Models\CarModel;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ListingIndexTest extends TestCase
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
            'admin' => ['admin'],
        ];
    }

    #[DataProvider('viewers')]
    public function test_the_index_is_open_to_every_viewer(string $viewer): void
    {
        $this->actAs($viewer);

        $this->get(route('listings.index'))
            ->assertOk()
            ->assertViewIs('pages.listings.index');
    }

    public function test_a_suspended_viewer_in_a_live_session_is_signed_out(): void
    {
        $this->actAs('suspended');

        $this->get(route('listings.index'))->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_the_spanish_kicker_is_marked_as_spanish(): void
    {
        $this->assertMatchesRegularExpression(
            '/lang="es"[^>]*>'.preg_quote(__('app.listings.index.kicker'), '/').'</',
            $this->get(route('listings.index'))->getContent(),
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
    public function test_only_public_listings_are_listed_for_every_viewer(string $viewer): void
    {
        $owner = User::factory()->create();
        $this->approved(['name' => 'Visible Bel Air']);
        CarListing::factory()->for($owner, 'owner')->create(['name' => 'Pending Bel Air']);
        CarListing::factory()->for($owner, 'owner')->rejected()->create(['name' => 'Rejected Bel Air']);
        CarListing::factory()->for(User::factory()->suspended(), 'owner')->approved()->create(['name' => 'Suspended Bel Air']);

        if ($viewer === 'owner') {
            $this->actingAs($owner);
        } else {
            $this->actAs($viewer);
        }

        $this->get(route('listings.index', ['search' => 'Bel Air']))
            ->assertOk()
            ->assertSee('Visible Bel Air')
            ->assertDontSee('Pending Bel Air')
            ->assertDontSee('Rejected Bel Air')
            ->assertDontSee('Suspended Bel Air');

        $this->get(route('listings.index'))
            ->assertSee('Visible Bel Air')
            ->assertDontSee('Pending Bel Air')
            ->assertDontSee('Rejected Bel Air')
            ->assertDontSee('Suspended Bel Air');
    }

    public function test_listings_are_newest_first_with_the_id_breaking_ties(): void
    {
        $this->approved(['name' => 'Oldest Car', 'created_at' => now()->subHours(2)]);
        $this->approved(['name' => 'Tied Earlier Id', 'created_at' => now()->subHour()]);
        $this->approved(['name' => 'Tied Later Id', 'created_at' => now()->subHour()]);

        $this->get(route('listings.index'))
            ->assertSeeInOrder(['Tied Later Id', 'Tied Earlier Id', 'Oldest Car']);
    }

    public function test_the_index_lists_twelve_per_page_and_page_two_holds_the_thirteenth(): void
    {
        $carModel = CarModel::factory()->create();

        foreach (range(1, 13) as $age) {
            $this->approved(['name' => "Listing Age {$age}", 'created_at' => now()->subMinutes($age)], $carModel);
        }

        $firstPage = $this->get(route('listings.index'));
        $this->assertCount(12, $firstPage->viewData('listings'));
        $firstPage->assertSee('Listing Age 12')->assertDontSee('Listing Age 13');

        $secondPage = $this->get(route('listings.index', ['page' => 2]));
        $this->assertSame(['Listing Age 13'], $secondPage->viewData('listings')->pluck('name')->all());
    }

    public function test_search_matches_the_name_the_maker_and_both_kinds_of_model_ignoring_case(): void
    {
        $maker = CarMaker::factory()->create(['name' => 'Zagatto Motors']);
        $listModel = CarModel::factory()->for($maker, 'maker')->create(['name' => 'Testarossa']);

        $this->approved(['name' => 'Silver Arrow']);
        $this->approved(['name' => 'Maker Match'], CarModel::factory()->for($maker, 'maker')->create());
        $this->approved(['name' => 'List Model Match'], $listModel);
        CarListing::factory()->approved()->customModel()->create(['name' => 'Custom Model Match', 'model' => 'Barchetta Special']);

        $this->assertSame(['Silver Arrow'], $this->namesFor(['search' => 'sILVER arrow']));
        $this->assertSame(['List Model Match', 'Maker Match'], $this->namesFor(['search' => 'zagatto']));
        $this->assertSame(['List Model Match'], $this->namesFor(['search' => 'TESTAROSSA']));
        $this->assertSame(['Custom Model Match'], $this->namesFor(['search' => 'barchetta']));
    }

    public function test_search_does_not_reveal_a_hidden_listing_that_matches(): void
    {
        $maker = CarMaker::factory()->create(['name' => 'Impala Works']);
        $this->approved(['name' => 'Public Impala']);
        CarListing::factory()->create(['name' => 'Pending Impala']);
        CarListing::factory()->forCarModel(CarModel::factory()->for($maker, 'maker')->create())->create(['name' => 'Pending By Maker']);
        CarListing::factory()->for(User::factory()->suspended(), 'owner')->approved()->customModel()
            ->create(['name' => 'Suspended By Model', 'model' => 'Impala Special']);

        $this->assertSame(['Public Impala'], $this->namesFor(['search' => 'Impala']));
    }

    public function test_percent_and_underscore_in_the_search_term_match_literally(): void
    {
        $this->approved(['name' => 'Original 100% Paint']);
        $this->approved(['name' => 'Original 1000 Paint']);
        $this->approved(['name' => 'Model A_B']);
        $this->approved(['name' => 'Model AXB']);

        $this->assertSame(['Original 100% Paint'], $this->namesFor(['search' => '100%']));
        $this->assertSame(['Model A_B'], $this->namesFor(['search' => 'A_B']));
    }

    public function test_a_blank_search_term_lists_everything_without_a_count(): void
    {
        $this->approved(['name' => 'First Car']);
        $this->approved(['name' => 'Second Car']);

        $response = $this->get(route('listings.index', ['search' => '   ']));

        $this->assertCount(2, $response->viewData('listings'));
        $response->assertDontSee('listings match');
    }

    public function test_the_maker_filter_limits_to_that_maker_and_combines_with_search(): void
    {
        $maker = CarMaker::factory()->create();
        $makerModel = CarModel::factory()->for($maker, 'maker')->create();
        $this->approved(['name' => 'Maker Coupe'], $makerModel);
        $this->approved(['name' => 'Maker Sedan'], $makerModel);
        $this->approved(['name' => 'Other Coupe']);

        $this->assertSame(['Maker Sedan', 'Maker Coupe'], $this->namesFor(['maker' => $maker->id]));
        $this->assertSame(['Maker Coupe'], $this->namesFor(['maker' => $maker->id, 'search' => 'Coupe']));
    }

    public function test_links_keep_the_other_filter_and_chip_links_drop_the_page(): void
    {
        $maker = CarMaker::factory()->create();
        $otherMaker = CarMaker::factory()->create();
        $carModel = CarModel::factory()->for($maker, 'maker')->create();

        foreach (range(1, 13) as $index) {
            $this->approved(['name' => "Match {$index}"], $carModel);
        }

        $response = $this->get(route('listings.index', ['search' => 'Match', 'maker' => $maker->id, 'page' => 2]));

        $response
            ->assertSee(e(route('listings.index', ['search' => 'Match', 'maker' => $maker->id, 'page' => 1])), false)
            ->assertSee(e(route('listings.index', ['search' => 'Match'])).'"', false)
            ->assertSee(e(route('listings.index', ['search' => 'Match', 'maker' => $otherMaker->id])).'"', false)
            ->assertSee('<input type="hidden" name="maker" value="'.$maker->id.'">', false);
    }

    public function test_chips_list_all_then_every_active_maker_by_name_with_one_active(): void
    {
        $alfa = CarMaker::factory()->create(['name' => 'Alfa Romeo']);
        CarMaker::factory()->create(['name' => 'Austin']);
        CarMaker::factory()->inactive()->create(['name' => 'Inactive Motors']);

        $unfiltered = $this->get(route('listings.index'))
            ->assertSeeInOrder([__('app.listings.index.all'), 'Alfa Romeo', 'Austin'])
            ->assertDontSee('Inactive Motors');
        $this->assertSame(1, substr_count($unfiltered->getContent(), 'aria-current="true"'));
        $this->assertMatchesRegularExpression('/aria-current="true"[^>]*>\s*<svg[^>]*>.*?<\/svg>\s*'.__('app.listings.index.all').'</s', $unfiltered->getContent());

        $filtered = $this->get(route('listings.index', ['maker' => $alfa->id]));
        $this->assertSame(1, substr_count($filtered->getContent(), 'aria-current="true"'));
        $this->assertMatchesRegularExpression('/aria-current="true"[^>]*>\s*<svg[^>]*>.*?<\/svg>\s*Alfa Romeo</s', $filtered->getContent());
    }

    public function test_the_chip_row_carries_the_hook_that_scrolls_the_active_chip_into_view(): void
    {
        $this->get(route('listings.index'))
            ->assertSee('<nav data-brand-chips aria-label="'.__('app.listings.index.chips_label').'"', false);
    }

    public function test_only_the_active_chip_carries_the_check_icon(): void
    {
        $alfa = CarMaker::factory()->create(['name' => 'Alfa Romeo']);
        CarMaker::factory()->create(['name' => 'Austin']);

        $chips = $this->get(route('listings.index', ['maker' => $alfa->id]))->getContent();
        $nav = Str::between($chips, 'aria-label="'.__('app.listings.index.chips_label').'"', '</nav>');

        $this->assertSame(1, substr_count($nav, '<svg'));
        $this->assertMatchesRegularExpression('/aria-current="true"[^>]*>\s*<svg[^>]*aria-hidden="true"/', $nav);
        $this->assertMatchesRegularExpression('/>Austin</', $nav);
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function invalidFilters(): array
    {
        return [
            'unknown maker' => [['maker' => 999999], 'maker'],
            'non-integer maker' => [['maker' => 'abc'], 'maker'],
            'array maker' => [['maker' => [1]], 'maker'],
            'search over 100 characters' => [['search' => str_repeat('a', 101)], 'search'],
            'array search' => [['search' => ['Bel Air']], 'search'],
        ];
    }

    /**
     * @param  array<string, mixed>  $query
     */
    #[DataProvider('invalidFilters')]
    public function test_an_invalid_filter_redirects_to_the_clean_index_with_the_error(array $query, string $field): void
    {
        $this->get(route('listings.index', $query))
            ->assertRedirect(route('listings.index'))
            ->assertSessionHasErrors($field);
    }

    public function test_an_inactive_maker_is_an_invalid_filter(): void
    {
        $maker = CarMaker::factory()->inactive()->create();

        $this->get(route('listings.index', ['maker' => $maker->id]))
            ->assertRedirect(route('listings.index'))
            ->assertSessionHasErrors('maker');
    }

    public function test_the_index_shows_the_validation_error_in_an_alert(): void
    {
        $this->followingRedirects()
            ->get(route('listings.index', ['search' => str_repeat('a', 101)]))
            ->assertOk()
            ->assertSee('role="alert"', false)
            ->assertSee(e(__('validation.max.string', ['attribute' => 'search', 'max' => 100])), false);
    }

    public function test_a_search_error_marks_the_search_input_and_points_it_at_the_alert(): void
    {
        $this->followingRedirects()
            ->get(route('listings.index', ['search' => str_repeat('a', 101)]))
            ->assertSee('id="listings-index-errors" role="alert"', false)
            ->assertSee('aria-invalid="true" aria-describedby="listings-index-errors"', false);
    }

    public function test_the_search_input_is_not_marked_without_a_search_error(): void
    {
        $this->get(route('listings.index'))
            ->assertDontSee('aria-invalid', false)
            ->assertDontSee('aria-describedby="listings-index-errors"', false);

        $this->followingRedirects()
            ->get(route('listings.index', ['maker' => 999999]))
            ->assertSee('id="listings-index-errors"', false)
            ->assertDontSee('aria-invalid', false);
    }

    public function test_a_non_numeric_page_renders_the_first_page(): void
    {
        $this->approved(['name' => 'Only Car']);

        $response = $this->get(route('listings.index', ['page' => 'abc']))->assertOk();

        $this->assertSame(1, $response->viewData('listings')->currentPage());
        $response->assertSee('Only Car');
    }

    public function test_the_count_line_renders_only_while_a_filter_is_active(): void
    {
        $maker = CarMaker::factory()->create();
        $carModel = CarModel::factory()->for($maker, 'maker')->create();
        $this->approved(['name' => 'Count Coupe'], $carModel);
        $this->approved(['name' => 'Count Sedan'], $carModel);

        $this->get(route('listings.index'))
            ->assertDontSee('listings match')
            ->assertDontSee('listing matches');

        $this->get(route('listings.index', ['search' => 'Count']))
            ->assertSee(trans_choice('app.listings.index.count', 2, ['count' => 2]));

        $this->get(route('listings.index', ['search' => 'Coupe', 'maker' => $maker->id]))
            ->assertSee(trans_choice('app.listings.index.count', 1, ['count' => 1]));
    }

    public function test_the_count_line_renders_for_a_maker_filter_alone(): void
    {
        $maker = CarMaker::factory()->create();
        $this->approved(['name' => 'Count Coupe'], CarModel::factory()->for($maker, 'maker')->create());

        $this->get(route('listings.index', ['maker' => $maker->id]))
            ->assertSee(trans_choice('app.listings.index.count', 1, ['count' => 1]));
    }

    public function test_a_search_of_exactly_100_characters_is_accepted(): void
    {
        $this->get(route('listings.index', ['search' => str_repeat('a', 100)]))
            ->assertOk()
            ->assertSessionHasNoErrors();
    }

    public function test_only_the_all_chip_renders_and_is_active_when_no_maker_is_active(): void
    {
        CarMaker::factory()->inactive()->create(['name' => 'Inactive Motors']);

        $nav = Str::between(
            $this->get(route('listings.index'))->getContent(),
            'aria-label="'.__('app.listings.index.chips_label').'"',
            '</nav>',
        );

        $this->assertSame(1, substr_count($nav, '<li'));
        $this->assertMatchesRegularExpression('/aria-current="true"[^>]*>\s*<svg[^>]*>.*?<\/svg>\s*'.__('app.listings.index.all').'</s', $nav);
    }

    public function test_the_unfiltered_empty_state_uses_the_source_message(): void
    {
        $this->get(route('listings.index'))
            ->assertOk()
            ->assertSee(__('app.listings.index.empty'))
            ->assertDontSee(__('app.listings.index.reset'));
    }

    public function test_a_filter_without_matches_shows_no_results_and_a_link_to_all_listings(): void
    {
        $this->approved(['name' => 'Bel Air']);

        $this->get(route('listings.index', ['search' => 'Countach']))
            ->assertOk()
            ->assertSee(__('app.listings.index.no_results'))
            ->assertSee('href="'.route('listings.index').'"', false)
            ->assertSee(__('app.listings.index.reset'))
            ->assertDontSee(__('app.listings.index.empty'));
    }

    public function test_a_page_beyond_the_last_renders_the_empty_state(): void
    {
        $this->approved(['name' => 'Bel Air']);

        $this->get(route('listings.index', ['page' => 9]))
            ->assertOk()
            ->assertSee(__('app.listings.index.empty'));
    }

    public function test_the_search_input_shows_the_trimmed_term(): void
    {
        $this->get(route('listings.index', ['search' => '  Bel Air  ']))
            ->assertSee('value="Bel Air"', false);
    }

    public function test_the_index_links_to_the_advanced_search_with_a_text_and_an_icon_link(): void
    {
        $html = $this->get(route('listings.index', ['search' => 'Bel']))->getContent();
        $advanced = preg_quote(route('listings.advanced'), '/');
        $label = preg_quote(__('app.listings.index.advanced_link'), '/');

        $this->assertMatchesRegularExpression('/<a href="'.$advanced.'" class="[^"]*md:inline">'.$label.'<\/a>/', $html);
        $this->assertMatchesRegularExpression('/<a href="'.$advanced.'" class="[^"]*md:hidden">\s*<svg[^>]*aria-hidden="true"[\s\S]*?<\/svg>\s*<span class="sr-only">'.$label.'<\/span>\s*<\/a>/', $html);
        $this->assertSame(2, substr_count($html, 'href="'.route('listings.advanced').'"'));
    }

    public function test_the_index_renders_cards_without_lazy_loading(): void
    {
        CarListing::factory()->count(3)->approved()->withImages(2)->create();

        $response = $this->get(route('listings.index'))->assertOk();

        $this->assertCount(3, $response->viewData('listings'));
    }

    public function test_the_query_count_does_not_grow_with_the_listings_on_the_page(): void
    {
        CarListing::factory()->count(2)->approved()->withImages()->create();
        $twoListings = $this->queriesFor(route('listings.index'));

        CarListing::factory()->count(10)->approved()->withImages()->create();
        $twelveListings = $this->queriesFor(route('listings.index'));

        $this->assertSame($twoListings, $twelveListings);
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

        if ($carModel !== null) {
            $factory = $factory->forCarModel($carModel);
        }

        return $factory->create($attributes);
    }

    /**
     * @param  array<string, mixed>  $query
     * @return list<string>
     */
    private function namesFor(array $query): array
    {
        return $this->get(route('listings.index', $query))
            ->assertOk()
            ->viewData('listings')
            ->pluck('name')
            ->all();
    }

    private function queriesFor(string $url): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->get($url)->assertOk();

        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }
}
