<?php

namespace Tests\Feature\Compare;

use App\Models\CarListing;
use App\Models\CarMaker;
use App\Models\CarModel;
use App\Services\CompareService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ComparePageTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_the_page_title_names_the_comparison(): void
    {
        $this->get(route('compare.show'))
            ->assertSee('<title>Compare Car Listings — Clásicos Autos</title>', false);
    }

    public function test_an_empty_comparison_shows_the_empty_box_without_clear_all_postcards_or_table(): void
    {
        $response = $this->get(route('compare.show'));

        $response->assertSee(__('app.compare.title'))
            ->assertSee(__('app.compare.empty'))
            ->assertDontSee(__('app.compare.clear_all'))
            ->assertDontSee('data-compare-postcard', false)
            ->assertDontSee('<table', false);
    }

    public function test_one_listing_shows_its_postcard_the_empty_slot_and_hyphens_on_the_right(): void
    {
        $listing = CarListing::factory()->approved()->create();
        $this->hold([$listing->id]);

        $response = $this->get(route('compare.show'));

        $response->assertSee($listing->name)
            ->assertSee(__('app.compare.clear_all'))
            ->assertSee('data-compare-empty-slot', false)
            ->assertDontSee('data-differs', false);
        $this->assertSame(1, substr_count((string) $response->getContent(), 'data-compare-postcard='));
        $this->assertSame(array_fill(0, 24, '-'), array_column($response->viewData('rows'), 'second'));
    }

    public function test_the_postcard_photo_is_decorative_because_its_heading_names_the_car(): void
    {
        $listing = CarListing::factory()->approved()->withImages()->create(['name' => 'Bel Air']);
        $this->hold([$listing->id]);

        $this->get(route('compare.show'))
            ->assertSee('src="'.$listing->images()->sole()->cardUrl().'" alt="" width="800"', false)
            ->assertDontSee('alt="Bel Air"', false);
    }

    public function test_clear_all_and_remove_are_48_px_touch_targets_below_md(): void
    {
        $listing = CarListing::factory()->approved()->create();
        $this->hold([$listing->id]);

        $html = (string) $this->get(route('compare.show'))->getContent();

        $this->assertMatchesRegularExpression('/<button type="submit" class="[^"]*min-h-12[^"]*md:min-h-9[^"]*">\s*Clear All\s*<\/button>/', $html);
        $this->assertMatchesRegularExpression('/<button type="submit" class="[^"]*min-h-12[^"]*md:min-h-9[^"]*">\s*Remove<span class="sr-only">/', $html);
    }

    public function test_two_listings_appear_in_the_order_they_were_added_not_database_order(): void
    {
        [$older, $newer] = CarListing::factory()->approved()->withImages()->count(2)->create();
        $this->hold([$newer->id, $older->id]);

        $response = $this->get(route('compare.show'));

        $response->assertSeeInOrder([$newer->name, $older->name])
            ->assertDontSee('data-compare-empty-slot', false);
        $this->assertSame([$newer->id, $older->id], $response->viewData('listings')->modelKeys());
    }

    public function test_each_postcard_removes_its_own_listing(): void
    {
        $listing = CarListing::factory()->approved()->create();
        $this->hold([$listing->id]);

        $response = $this->get(route('compare.show'));

        $response->assertSee('action="'.route('compare.remove', $listing).'"', false)
            ->assertSee('name="_method" value="DELETE"', false);
    }

    public function test_a_held_listing_that_stopped_being_public_is_dropped_silently_and_pruned(): void
    {
        $kept = CarListing::factory()->approved()->create();
        $hidden = CarListing::factory()->rejected()->create(['name' => 'Hidden Rejected Coupe']);
        $this->hold([$hidden->id, $kept->id]);

        $response = $this->get(route('compare.show'));

        $response->assertSee($kept->name)
            ->assertDontSee('Hidden Rejected Coupe')
            ->assertSessionMissing('error');
        $this->assertSame([$kept->id], $this->held());
    }

    public function test_an_id_whose_listing_was_deleted_is_pruned(): void
    {
        $kept = CarListing::factory()->approved()->create();
        $deleted = CarListing::factory()->approved()->create();
        $deletedId = $deleted->id;
        $deleted->delete();
        $this->hold([$kept->id, $deletedId]);

        $this->get(route('compare.show'));

        $this->assertSame([$kept->id], $this->held());
    }

    public function test_a_differing_row_is_red_with_the_not_equal_mark_and_a_hidden_note(): void
    {
        $first = $this->listingOf('Chevrolet', ['year' => 1957]);
        $second = $this->listingOf('Ford', ['year' => 1957]);
        $this->hold([$first->id, $second->id]);

        $html = (string) $this->get(route('compare.show'))->getContent();

        $this->assertMatchesRegularExpression(
            '/<tr[^>]*data-differs\s*>\s*<td role="cell" class="[^"]*\btext-red\b[^"]*">Chevrolet<\/td>\s*<th role="rowheader" scope="row"[^>]*>\s*<span aria-hidden="true"[^>]*>≠<\/span>\s*Car Maker\s*<span class="sr-only">differs<\/span>\s*<\/th>\s*<td role="cell" class="[^"]*\btext-red\b[^"]*">Ford<\/td>/',
            $html,
        );
        $this->assertMatchesRegularExpression(
            '/<tr[^>]*md:table-row"\s*>\s*<td role="cell" class="[^"]*\btext-ink\b[^"]*">1957<\/td>\s*<th role="rowheader" scope="row"[^>]*>\s*Year\s*<\/th>/',
            $html,
        );
    }

    public function test_the_table_names_both_listings_in_hidden_column_headers(): void
    {
        [$first, $second] = CarListing::factory()->approved()->count(2)->create();
        $this->hold([$first->id, $second->id]);

        $response = $this->get(route('compare.show'));

        $response->assertSeeInOrder(['<table role="table"', '<thead role="rowgroup" class="sr-only">', '<th role="columnheader" scope="col">'.e($first->name).'</th>', '<th role="columnheader" scope="col">'.e($second->name).'</th>'], false);
    }

    public function test_an_empty_comparison_renders_without_a_query(): void
    {
        $this->expectsDatabaseQueryCount(0);

        $this->get(route('compare.show'));
    }

    /**
     * @param  list<int>  $ids
     */
    private function hold(array $ids): void
    {
        $this->withSession([CompareService::SESSION_KEY => $ids]);
    }

    /**
     * @return list<int>|null
     */
    private function held(): ?array
    {
        return $this->app['session.store']->get(CompareService::SESSION_KEY);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function listingOf(string $makerName, array $attributes): CarListing
    {
        $carModel = CarModel::factory()->for(CarMaker::factory()->state(['name' => $makerName]), 'maker')->create(['year_from' => 1950, 'year_to' => 1981]);

        return CarListing::factory()->approved()->forCarModel($carModel)->create($attributes);
    }
}
