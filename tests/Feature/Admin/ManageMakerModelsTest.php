<?php

namespace Tests\Feature\Admin;

use App\Enums\ListingStatus;
use App\Exceptions\ReferenceDataInUseException;
use App\Models\CarListing;
use App\Models\CarMaker;
use App\Models\CarModel;
use App\Models\User;
use App\Services\ReferenceDataService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ManageMakerModelsTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_an_admin_adds_an_active_model_under_the_maker(): void
    {
        $maker = CarMaker::factory()->create();

        $this->asAdmin()->post(route('admin.makers.models.store', $maker), ['name' => 'Bel Air', 'year_from' => 1950, 'year_to' => 1975, 'is_active' => '0'])
            ->assertRedirect(route('admin.makers.show', $maker))
            ->assertSessionHas('success', 'The model Bel Air has been added.');

        $model = CarModel::query()->sole();
        $this->assertSame([$maker->id, 'Bel Air', 1950, 1975, true], [$model->car_maker_id, $model->name, $model->year_from, $model->year_to, $model->is_active]);
    }

    public function test_a_model_may_be_added_under_an_inactive_maker(): void
    {
        $maker = CarMaker::factory()->inactive()->create();

        $this->asAdmin()->post(route('admin.makers.models.store', $maker), ['name' => 'Golden Hawk', 'year_from' => 1956, 'year_to' => 1958])
            ->assertSessionHasNoErrors();

        $this->assertSame($maker->id, CarModel::query()->sole()->car_maker_id);
    }

    public function test_a_name_may_repeat_across_makers_but_not_under_the_same_maker(): void
    {
        $maker = CarMaker::factory()->create();
        CarModel::factory()->for($maker, 'maker')->create(['name' => 'Bel Air']);
        $other = CarMaker::factory()->create();

        $this->asAdmin()->post(route('admin.makers.models.store', $other), ['name' => 'Bel Air', 'year_from' => 1950, 'year_to' => 1960])
            ->assertSessionHasNoErrors();

        $this->asAdmin()->post(route('admin.makers.models.store', $maker), ['name' => 'Bél air', 'year_from' => 1950, 'year_to' => 1960])
            ->assertRedirect(route('admin.makers.show', $maker))
            ->assertSessionHasErrors(['name' => 'The name has already been taken.']);

        $this->assertSame(1, $maker->models()->count());
    }

    public function test_years_must_be_classic_and_in_order(): void
    {
        $maker = CarMaker::factory()->create();

        $this->asAdmin()->post(route('admin.makers.models.store', $maker), ['name' => 'Late', 'year_from' => 1899, 'year_to' => 1990])
            ->assertSessionHasErrors([
                'year_from' => 'The year from field must be between 1900 and 1989.',
                'year_to' => 'The year to field must be between 1900 and 1989.',
            ]);

        $this->asAdmin()->post(route('admin.makers.models.store', $maker), ['name' => 'Backwards', 'year_from' => 1960, 'year_to' => 1955])
            ->assertSessionHasErrors(['year_to' => 'The year to field must be greater than or equal to 1960.']);

        $this->asAdmin()->post(route('admin.makers.models.store', $maker), [])
            ->assertSessionHasErrors(['name', 'year_from', 'year_to']);

        $this->assertSame(0, CarModel::query()->count());
    }

    public function test_an_admin_updates_a_model_without_listings(): void
    {
        $maker = CarMaker::factory()->create();
        $model = CarModel::factory()->for($maker, 'maker')->create(['name' => 'Nomad', 'year_from' => 1955, 'year_to' => 1961]);

        $this->asAdmin()->patch(route('admin.makers.models.update', [$maker, $model]), ['name' => 'Nomad Wagon', 'year_from' => 1900, 'year_to' => 1901, 'is_active' => '0'])
            ->assertRedirect(route('admin.makers.show', $maker))
            ->assertSessionHas('success', 'The model Nomad Wagon has been saved.');

        $model->refresh();
        $this->assertSame(['Nomad Wagon', 1900, 1901, true], [$model->name, $model->year_from, $model->year_to, $model->is_active]);
    }

    public function test_renaming_rewrites_every_linked_listing_without_touching_its_review_state(): void
    {
        $this->travelTo('2026-10-16 10:00:00');
        $maker = CarMaker::factory()->create();
        $model = CarModel::factory()->for($maker, 'maker')->create(['name' => 'Bel Air', 'year_from' => 1950, 'year_to' => 1975]);
        $approved = CarListing::factory()->approved()->forCarModel($model)->create(['name' => 'Two-tone coupe', 'year' => 1957]);
        $pending = CarListing::factory()->forCarModel($model)->create(['submitted_at' => now()->subDay()]);
        $rejected = CarListing::factory()->rejected()->forCarModel($model)->create();
        $custom = CarListing::factory()->customModel()->for($maker, 'maker')->create(['model' => 'Bel Air']);
        $before = $this->reviewState([$approved, $pending, $rejected, $custom]);
        $version = $pending->moderationVersion();

        $this->travel(1)->hours();

        $this->asAdmin()->patch(route('admin.makers.models.update', [$maker, $model]), ['name' => 'Bel Air Sport', 'year_from' => 1950, 'year_to' => 1975])
            ->assertSessionHasNoErrors();

        $this->assertSame(['Bel Air Sport', 'Bel Air Sport', 'Bel Air Sport', 'Bel Air'], [$approved->refresh()->model, $pending->refresh()->model, $rejected->refresh()->model, $custom->refresh()->model]);
        $this->assertSame($before, $this->reviewState([$approved, $pending, $rejected, $custom]));

        $this->asAdmin()->patch(route('admin.listings.approve', $pending), ['version' => $version])->assertSessionHasNoErrors()->assertSessionMissing('error');
        $this->assertSame(ListingStatus::Approved, $pending->refresh()->status);

        $this->app['auth']->forgetGuards();
        $this->assertContains($approved->id, $this->get(route('listings.index', ['search' => 'Sport']))->viewData('listings')->pluck('id')->all());
    }

    public function test_a_casing_only_rename_also_reaches_the_listings(): void
    {
        $maker = CarMaker::factory()->create();
        $model = CarModel::factory()->for($maker, 'maker')->create(['name' => 'Bel air', 'year_from' => 1950, 'year_to' => 1975]);
        $listing = CarListing::factory()->forCarModel($model)->create();

        $this->asAdmin()->patch(route('admin.makers.models.update', [$maker, $model]), ['name' => 'Bel Air', 'year_from' => 1950, 'year_to' => 1975])
            ->assertSessionHasNoErrors();

        $this->assertSame('Bel Air', $listing->refresh()->model);
    }

    public function test_narrowing_below_a_linked_listings_year_is_refused_on_year_from(): void
    {
        [$maker, $model] = $this->modelWithListingYears([1957, 1955, 1955, 1960]);

        $this->asAdmin()->patch(route('admin.makers.models.update', [$maker, $model]), ['name' => 'Renamed', 'year_from' => 1958, 'year_to' => 1965])
            ->assertRedirect(route('admin.makers.show', $maker))
            ->assertSessionHasErrors(['year_from' => 'Listings of this model are from 1955, 1957, so the first year cannot be later.'], errorBag: "model-{$model->id}");

        $this->assertUnchanged($model);
    }

    public function test_narrowing_above_a_linked_listings_year_is_refused_on_year_to(): void
    {
        [$maker, $model] = $this->modelWithListingYears([1957, 1960, 1962]);

        $response = $this->asAdmin()->patch(route('admin.makers.models.update', [$maker, $model]), ['name' => 'Bel Air', 'year_from' => 1950, 'year_to' => 1959])
            ->assertSessionHasErrors(['year_to' => 'Listings of this model are from 1960, 1962, so the last year cannot be earlier.'], errorBag: "model-{$model->id}");

        $this->assertArrayNotHasKey('year_from', $response->getSession()->get('errors')->getBag("model-{$model->id}")->messages());
        $this->assertUnchanged($model);
    }

    public function test_widening_is_accepted_and_custom_listings_of_the_maker_do_not_count(): void
    {
        [$maker, $model] = $this->modelWithListingYears([1957]);
        CarListing::factory()->customModel()->for($maker, 'maker')->create(['year' => 1920]);

        $this->asAdmin()->patch(route('admin.makers.models.update', [$maker, $model]), ['name' => 'Bel Air', 'year_from' => 1940, 'year_to' => 1989])
            ->assertSessionHasNoErrors();

        $this->assertSame([1940, 1989], [$model->refresh()->year_from, $model->year_to]);
    }

    public function test_a_failed_model_edit_reopens_its_dialog_on_the_invalid_year(): void
    {
        [$maker, $model] = $this->modelWithListingYears([1957]);
        $dialog = 'edit-model-'.$model->id;

        $html = $this->asAdmin()->followingRedirects()
            ->patch(route('admin.makers.models.update', [$maker, $model]), ['name' => 'Bel Air', 'year_from' => 1960, 'year_to' => 1965])
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression('/<dialog id="'.$dialog.'"[^>]*data-dialog-autoopen/', $html);
        $this->assertMatchesRegularExpression('/<input id="'.$dialog.'-year-from" name="year_from" type="number"\s+value="1960"[^>]*aria-invalid="true"/', $html);
        $this->assertMatchesRegularExpression('/<input id="'.$dialog.'-year-to" name="year_to" type="number"\s+value="1965"/', $html);
        $this->assertStringNotContainsString('data-error-summary', $html);
    }

    public function test_an_inactive_model_leaves_the_create_form_but_stays_on_its_listings_edit_form(): void
    {
        $maker = CarMaker::factory()->create();
        $model = CarModel::factory()->for($maker, 'maker')->create();
        $owner = User::factory()->create();
        $listing = CarListing::factory()->forCarModel($model)->for($owner, 'owner')->create();

        foreach ([1, 2] as $attempt) {
            $this->asAdmin()->patch(route('admin.makers.models.active', [$maker, $model]), ['active' => '0'])
                ->assertRedirect(route('admin.makers.show', $maker))
                ->assertSessionHas('success', "{$model->name} is now inactive. It no longer appears in the listing form.");
        }

        $this->assertFalse($model->refresh()->is_active);
        $this->app['auth']->forgetGuards();
        $this->assertNotContains($model->id, $this->modelIds($this->actingAs($owner)->get(route('listings.create'))->viewData('options')->modelConfig['models'][$maker->id]));
        $this->assertContains($model->id, $this->modelIds($this->actingAs($owner)->get(route('listings.edit', $listing))->viewData('options')->modelConfig['models'][$maker->id]));

        $this->asAdmin()->patch(route('admin.makers.models.active', [$maker, $model]), ['active' => '1'])
            ->assertSessionHas('success', "{$model->name} is active again.");

        $this->assertTrue($model->refresh()->is_active);
        $this->app['auth']->forgetGuards();
        $this->assertContains($model->id, $this->modelIds($this->actingAs($owner)->get(route('listings.create'))->viewData('options')->modelConfig['models'][$maker->id]));
    }

    public function test_a_model_toggle_without_a_valid_state_returns_to_the_models_page_with_the_error(): void
    {
        $model = CarModel::factory()->create();

        $this->asAdmin()->patch(route('admin.makers.models.active', [$model->car_maker_id, $model]), [])
            ->assertRedirect(route('admin.makers.show', $model->car_maker_id))
            ->assertSessionHas('error', 'The status field is required.');
    }

    public function test_an_unreferenced_model_is_deleted_and_a_referenced_one_is_forbidden(): void
    {
        $maker = CarMaker::factory()->create();
        $unused = CarModel::factory()->for($maker, 'maker')->create(['name' => 'Nomad']);
        $used = CarModel::factory()->for($maker, 'maker')->create();
        CarListing::factory()->rejected()->forCarModel($used)->create();

        $this->asAdmin()->delete(route('admin.makers.models.destroy', [$maker, $unused]))
            ->assertRedirect(route('admin.makers.show', $maker))
            ->assertSessionHas('success', 'The model Nomad has been deleted.');
        $this->asAdmin()->delete(route('admin.makers.models.destroy', [$maker, $used]))->assertForbidden();

        $this->assertModelMissing($unused);
        $this->assertModelExists($used);
    }

    public function test_the_service_refuses_a_model_that_gained_a_listing_after_the_check(): void
    {
        $model = CarModel::factory()->create();
        $this->assertTrue($model->isDeletable());
        CarListing::factory()->forCarModel($model)->create();

        $thrown = null;

        try {
            app(ReferenceDataService::class)->deleteModel($model);
        } catch (ReferenceDataInUseException $exception) {
            $thrown = $exception;
        }

        $this->assertInstanceOf(ReferenceDataInUseException::class, $thrown);
        $this->assertModelExists($model);
    }

    public function test_the_controller_turns_the_in_use_guard_into_an_error_flash(): void
    {
        $maker = CarMaker::factory()->create();
        $model = CarModel::factory()->for($maker, 'maker')->create(['name' => 'Nomad']);
        $this->mock(ReferenceDataService::class)->shouldReceive('deleteModel')->once()->andThrow(new ReferenceDataInUseException('in use'));

        $this->asAdmin()->delete(route('admin.makers.models.destroy', [$maker, $model]))
            ->assertRedirect(route('admin.makers.show', $maker))
            ->assertSessionHas('error', 'Nomad is still used by listings, so it was not deleted. Deactivate it instead.');

        $this->assertModelExists($model);
    }

    public function test_an_app_users_invalid_model_write_is_refused_before_validation(): void
    {
        $maker = CarMaker::factory()->create();
        $model = CarModel::factory()->for($maker, 'maker')->create(['name' => 'Kept']);
        $member = User::factory()->create();

        $this->actingAs($member)->post(route('admin.makers.models.store', $maker), ['year_from' => 'x'])
            ->assertForbidden()->assertSessionDoesntHaveErrors();
        $this->actingAs($member)->patch(route('admin.makers.models.update', [$maker, $model]), ['year_from' => 'x'])
            ->assertForbidden()->assertSessionDoesntHaveErrors();

        $this->assertSame(['Kept'], CarModel::query()->pluck('name')->all());
    }

    /**
     * @param  list<int>  $years
     * @return array{CarMaker, CarModel}
     */
    private function modelWithListingYears(array $years): array
    {
        $maker = CarMaker::factory()->create();
        $model = CarModel::factory()->for($maker, 'maker')->create(['name' => 'Bel Air', 'year_from' => 1950, 'year_to' => 1965]);

        foreach ($years as $year) {
            CarListing::factory()->forCarModel($model)->create(['year' => $year]);
        }

        return [$maker, $model];
    }

    private function assertUnchanged(CarModel $model): void
    {
        $model->refresh();

        $this->assertSame(['Bel Air', 1950, 1965], [$model->name, $model->year_from, $model->year_to]);
        $this->assertSame(['Bel Air'], $model->listings()->distinct()->pluck('model')->all());
    }

    /**
     * @param  list<CarListing>  $listings
     * @return list<array{string, string, ?string}>
     */
    private function reviewState(array $listings): array
    {
        return array_map(fn (CarListing $listing): array => [
            $listing->refresh()->status->value,
            $listing->updated_at->toIso8601String(),
            $listing->submitted_at?->toIso8601String(),
        ], $listings);
    }

    /**
     * @param  list<array{id: int, name: string}>  $models
     * @return list<int>
     */
    private function modelIds(array $models): array
    {
        return array_column($models, 'id');
    }

    private function asAdmin(): static
    {
        $this->app['auth']->forgetGuards();

        return $this->actingAs(User::factory()->admin()->create());
    }
}
