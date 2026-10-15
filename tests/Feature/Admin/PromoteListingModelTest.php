<?php

namespace Tests\Feature\Admin;

use App\Enums\ListingStatus;
use App\Models\CarListing;
use App\Models\CarMaker;
use App\Models\CarModel;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class PromoteListingModelTest extends TestCase
{
    use LazilyRefreshDatabase;

    private CarMaker $maker;

    public function test_a_new_name_creates_an_active_model_and_links_the_listing_without_moving_it_in_the_queue(): void
    {
        $this->travelTo('2026-10-15 10:00:00');
        $listing = $this->customListing(['submitted_at' => now()->subDays(2)]);
        $version = $listing->moderationVersion();
        $this->travel(1)->seconds();

        $this->promote($listing, ['name' => 'Roadster', 'year_from' => 1955, 'year_to' => 1960])
            ->assertRedirect(route('admin.listings'))
            ->assertSessionHas('success', 'Roadster was added to the model list and linked to this listing.');

        $model = CarModel::query()->where('car_maker_id', $this->maker->id)->sole();
        $listing->refresh();
        $this->assertSame(['Roadster', 1955, 1960, true], [$model->name, $model->year_from, $model->year_to, $model->is_active]);
        $this->assertSame([$model->id, 'Roadster'], [$listing->car_model_id, $listing->model]);
        $this->assertSame(ListingStatus::Pending, $listing->status);
        $this->assertSame('2026-10-13 10:00:00', $listing->submitted_at->toDateTimeString());
        $this->assertSame([null, null, null], [$listing->approved_at, $listing->rejected_at, $listing->rejection_reason]);
        $this->assertGreaterThan($version, $listing->moderationVersion());
    }

    public function test_an_approve_rendered_before_the_promotion_is_refused_as_changed(): void
    {
        $listing = $this->customListing();
        $version = $listing->moderationVersion();
        $this->travel(1)->seconds();
        $this->promote($listing, ['name' => 'Roadster', 'year_from' => 1955, 'year_to' => 1960]);

        $this->actingAs(User::factory()->admin()->create())
            ->patch(route('admin.listings.approve', [$listing, 'return' => 'queue']), ['version' => $version])
            ->assertRedirect(route('admin.listings'))
            ->assertSessionHas('error', 'This listing changed after you opened it. Review it again.');

        $this->assertTrue($listing->fresh()->isPending());
    }

    public function test_a_name_matching_an_existing_model_case_and_accent_insensitively_links_it(): void
    {
        $belAir = CarModel::factory()->for($this->maker(), 'maker')->create(['name' => 'Bel Air', 'year_from' => 1950, 'year_to' => 1981]);

        foreach (['bel air', 'Bél Air'] as $typed) {
            $listing = $this->customListing();

            $this->promote($listing, ['name' => $typed, 'year_from' => 1957, 'year_to' => 1957])
                ->assertSessionHas('success', 'This listing is now linked to Bel Air.');

            $this->assertSame([$belAir->id, 'Bel Air'], [$listing->fresh()->car_model_id, $listing->fresh()->model]);
        }

        $this->assertSame(1, CarModel::query()->count());
        $this->assertSame([1950, 1981], [$belAir->fresh()->year_from, $belAir->fresh()->year_to]);
    }

    public function test_an_inactive_match_is_reactivated_and_linked(): void
    {
        $belAir = CarModel::factory()->for($this->maker(), 'maker')->create(['name' => 'Bel Air', 'year_from' => 1950, 'year_to' => 1981]);
        $belAir->is_active = false;
        $belAir->save();
        $listing = $this->customListing();

        $this->promote($listing, ['name' => 'Bel Air', 'year_from' => 1957, 'year_to' => 1957])
            ->assertSessionHas('success', 'Bel Air was reactivated and linked to this listing.');

        $this->assertTrue($belAir->fresh()->is_active);
        $this->assertSame($belAir->id, $listing->fresh()->car_model_id);
    }

    public function test_a_model_of_another_maker_is_never_matched(): void
    {
        CarModel::factory()->create(['name' => 'Roadster', 'year_from' => 1900, 'year_to' => 1910]);
        $listing = $this->customListing();

        $this->promote($listing, ['name' => 'Roadster', 'year_from' => 1957, 'year_to' => 1957])
            ->assertSessionHas('success', 'Roadster was added to the model list and linked to this listing.');

        $this->assertSame($this->maker->id, $listing->fresh()->carModel->car_maker_id);
    }

    public function test_a_match_whose_years_exclude_the_listing_year_is_refused_naming_those_years(): void
    {
        CarModel::factory()->for($this->maker(), 'maker')->create(['name' => 'Bel Air', 'year_from' => 1950, 'year_to' => 1955]);
        $listing = $this->customListing();

        $this->promote($listing, ['name' => 'bel air', 'year_from' => 1950, 'year_to' => 1960])
            ->assertRedirect(route('admin.listings'))
            ->assertSessionHas('error', 'Bel Air already exists with production years 1950–1955, which do not include 1957.');

        $this->assertUnchanged($listing);
    }

    public function test_new_years_that_exclude_the_listing_year_are_refused(): void
    {
        $listing = $this->customListing();

        $this->promote($listing, ['name' => 'Roadster', 'year_from' => 1960, 'year_to' => 1970])
            ->assertSessionHas('error', 'The production years must include 1957.');

        $this->assertUnchanged($listing);
        $this->assertSame(0, CarModel::query()->count());
    }

    public function test_the_field_rules_refuse_with_their_first_error_and_skip_the_year_check(): void
    {
        $listing = $this->customListing();

        $this->promote($listing, ['name' => 'Roadster', 'year_from' => 1960, 'year_to' => 1950])
            ->assertSessionHas('error', 'The production to field must be greater than or equal to 1960.');
        $this->promote($listing, ['name' => str_repeat('a', 65), 'year_from' => 1957, 'year_to' => 1957])
            ->assertSessionHas('error', 'The model name field must not be greater than 64 characters.');
        $this->promote($listing, ['name' => 'Roadster', 'year_from' => 1899, 'year_to' => 1957])
            ->assertSessionHas('error', 'The production from field must be between 1900 and 1989.');
        $this->promote($listing, ['year_from' => 1960, 'year_to' => 1970])
            ->assertRedirect(route('admin.listings'))
            ->assertSessionHas('error', 'The model name field is required.')
            ->assertSessionDoesntHaveErrors();

        $this->assertUnchanged($listing);
        $this->assertSame(0, CarModel::query()->count());
    }

    public function test_a_listing_that_is_not_pending_or_has_a_list_model_is_not_promotable(): void
    {
        $approved = $this->customListing();
        $approved->status = ListingStatus::Approved;
        $approved->save();
        $listModel = CarListing::factory()->create();

        foreach ([$approved, $listModel] as $listing) {
            $this->promote($listing, ['name' => 'Roadster', 'year_from' => 1900, 'year_to' => 1989])
                ->assertRedirect(route('admin.listings'))
                ->assertSessionHas('error', 'Only a pending listing with a custom model can be promoted.');
        }

        $this->assertSame(1, CarModel::query()->count());
        $this->assertNull($approved->fresh()->car_model_id);
    }

    public function test_an_approved_listing_gets_not_promotable_even_when_a_match_excludes_its_year(): void
    {
        CarModel::factory()->for($this->maker(), 'maker')->create(['name' => 'Bel Air', 'year_from' => 1950, 'year_to' => 1955]);
        $approved = $this->customListing();
        $approved->status = ListingStatus::Approved;
        $approved->save();

        $this->promote($approved, ['name' => 'Bel Air', 'year_from' => 1957, 'year_to' => 1957])
            ->assertSessionHas('error', 'Only a pending listing with a custom model can be promoted.');
    }

    public function test_a_stale_version_is_refused_as_changed(): void
    {
        $listing = $this->customListing();

        $this->promote($listing, ['name' => 'Roadster', 'year_from' => 1955, 'year_to' => 1960, 'version' => $listing->moderationVersion() - 1])
            ->assertSessionHas('error', 'This listing changed after you opened it. Review it again.');

        $this->assertUnchanged($listing);
        $this->assertSame(0, CarModel::query()->count());
    }

    public function test_the_redirect_keeps_the_valid_queue_view(): void
    {
        $listing = $this->customListing();

        $this->promote($listing, ['name' => 'Roadster', 'year_from' => 1955, 'year_to' => 1960], ['return' => 'queue', 'status' => 'all', 'search' => 'road', 'page' => 2, 'evil' => 'x'])
            ->assertRedirect(route('admin.listings', ['status' => 'all', 'search' => 'road', 'page' => 2]));
    }

    public function test_server_controlled_keys_in_the_payload_are_ignored(): void
    {
        $this->travelTo('2026-10-15 10:00:00');
        $listing = $this->customListing(['submitted_at' => now()->subDay()]);

        $this->promote($listing, [
            'name' => 'Roadster', 'year_from' => 1955, 'year_to' => 1960,
            'status' => 'approved', 'car_model_id' => 999, 'submitted_at' => '2000-01-01', 'is_active' => '0',
        ])->assertSessionHas('success');

        $listing->refresh();
        $this->assertSame(ListingStatus::Pending, $listing->status);
        $this->assertSame('2026-10-14 10:00:00', $listing->submitted_at->toDateTimeString());
        $this->assertNotSame(999, $listing->car_model_id);
        $this->assertTrue($listing->carModel->is_active);
    }

    public function test_an_app_user_is_refused_before_validation_with_403_and_an_unknown_id_with_404(): void
    {
        $listing = $this->customListing();
        $appUser = User::factory()->create();

        $this->actingAs($appUser)->patch(route('admin.listings.promote', $listing), [])
            ->assertForbidden()
            ->assertSessionDoesntHaveErrors();
        $this->actingAs($appUser)->patch(route('admin.listings.promote', $listing->id + 100), [])
            ->assertNotFound();
        $this->actingAs(User::factory()->admin()->create())->patch(route('admin.listings.promote', $listing->id + 100), [])
            ->assertNotFound();
        $this->actingAs(User::factory()->admin()->create())->patch('/admin/listings/abc/promote-model', [])
            ->assertNotFound();

        $this->assertUnchanged($listing);
    }

    public function test_the_owner_of_the_listing_is_refused_too(): void
    {
        $listing = $this->customListing();

        $this->actingAs($listing->owner)->patch(route('admin.listings.promote', $listing), [])
            ->assertForbidden()
            ->assertSessionDoesntHaveErrors();
    }

    public function test_a_guest_is_sent_to_login(): void
    {
        $listing = $this->customListing();

        $this->patch(route('admin.listings.promote', $listing), [])->assertRedirect(route('login'));
    }

    private function maker(): CarMaker
    {
        return $this->maker ??= CarMaker::factory()->create();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function customListing(array $attributes = []): CarListing
    {
        return CarListing::factory()->customModel()->create(['car_maker_id' => $this->maker()->id, 'model' => 'Roadster', 'year' => 1957, ...$attributes]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $query
     */
    private function promote(CarListing $listing, array $payload, array $query = []): TestResponse
    {
        $response = $this->actingAs(User::factory()->admin()->create())
            ->patch(route('admin.listings.promote', [$listing, ...$query]), ['version' => $listing->fresh()->moderationVersion(), ...$payload]);
        $this->app['auth']->forgetGuards();

        return $response;
    }

    private function assertUnchanged(CarListing $listing): void
    {
        $fresh = $listing->fresh();

        $this->assertNull($fresh->car_model_id);
        $this->assertSame($listing->model, $fresh->model);
        $this->assertSame(ListingStatus::Pending, $fresh->status);
        $this->assertSame($listing->moderationVersion(), $fresh->moderationVersion());
    }
}
