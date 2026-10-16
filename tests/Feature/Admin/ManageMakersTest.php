<?php

namespace Tests\Feature\Admin;

use App\Exceptions\ReferenceDataInUseException;
use App\Models\CarListing;
use App\Models\CarMaker;
use App\Models\CarModel;
use App\Models\User;
use App\Services\ReferenceDataService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ManageMakersTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_an_admin_adds_an_active_maker(): void
    {
        $this->asAdmin()->post(route('admin.makers.store'), ['name' => 'Tucker'])
            ->assertRedirect(route('admin.makers'))
            ->assertSessionHas('success', 'The maker Tucker has been added.');

        $this->assertTrue(CarMaker::query()->where('name', 'Tucker')->sole()->is_active);
    }

    public function test_a_posted_is_active_is_ignored_on_add_and_rename(): void
    {
        $this->asAdmin()->post(route('admin.makers.store'), ['name' => 'Tucker', 'is_active' => '0']);
        $maker = CarMaker::query()->where('name', 'Tucker')->sole();

        $this->asAdmin()->patch(route('admin.makers.update', $maker), ['name' => 'Tucker Corp', 'is_active' => '0']);

        $this->assertTrue($maker->refresh()->is_active);
        $this->assertSame('Tucker Corp', $maker->name);
    }

    public function test_an_empty_or_long_name_is_refused(): void
    {
        $this->asAdmin()->post(route('admin.makers.store'), ['name' => ''])
            ->assertRedirect(route('admin.makers'))
            ->assertSessionHasErrors(['name' => 'The name field is required.']);

        $this->asAdmin()->post(route('admin.makers.store'), ['name' => str_repeat('a', 65)])
            ->assertSessionHasErrors(['name' => 'The name field must not be greater than 64 characters.']);

        $this->assertSame(0, CarMaker::query()->count());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function collidingNames(): array
    {
        return [
            'same name' => ['Bel Air Motors'],
            'other casing' => ['bel air motors'],
            'accented' => ['Bél Air Motors'],
        ];
    }

    #[DataProvider('collidingNames')]
    public function test_a_name_colliding_case_or_accent_insensitively_is_refused(string $name): void
    {
        CarMaker::factory()->create(['name' => 'Bel Air Motors']);

        $this->asAdmin()->post(route('admin.makers.store'), ['name' => $name])
            ->assertRedirect(route('admin.makers'))
            ->assertSessionHasErrors(['name' => 'The name has already been taken.']);

        $this->assertSame(1, CarMaker::query()->count());
    }

    public function test_renaming_changes_only_the_maker_row_and_redirects_with_success(): void
    {
        $maker = CarMaker::factory()->create(['name' => 'Studebaker']);
        $listing = CarListing::factory()->customModel()->for($maker, 'maker')->create();
        $updatedAt = $listing->updated_at->toIso8601String();

        $this->travel(1)->hours();

        $this->asAdmin()->patch(route('admin.makers.update', $maker), ['name' => 'Studebaker-Packard'])
            ->assertRedirect(route('admin.makers'))
            ->assertSessionHas('success', 'The maker Studebaker-Packard has been saved.');

        $this->assertSame('Studebaker-Packard', $maker->refresh()->name);
        $this->assertSame($updatedAt, $listing->refresh()->updated_at->toIso8601String());
    }

    public function test_a_maker_can_be_renamed_to_another_casing_of_its_own_name(): void
    {
        $maker = CarMaker::factory()->create(['name' => 'Desoto']);

        $this->asAdmin()->patch(route('admin.makers.update', $maker), ['name' => 'DeSoto'])
            ->assertSessionHasNoErrors();

        $this->assertSame('DeSoto', $maker->refresh()->name);
    }

    public function test_a_failed_rename_flashes_into_the_rows_bag_and_changes_nothing(): void
    {
        CarMaker::factory()->create(['name' => 'Packard']);
        $maker = CarMaker::factory()->create(['name' => 'Hudson']);

        $this->asAdmin()->patch(route('admin.makers.update', $maker), ['name' => 'packard'])
            ->assertRedirect(route('admin.makers'))
            ->assertSessionHasErrors(['name' => 'The name has already been taken.'], errorBag: "maker-{$maker->id}");

        $this->assertSame('Hudson', $maker->refresh()->name);
    }

    public function test_a_failed_rename_reopens_only_its_dialog_with_the_error_and_old_input(): void
    {
        CarMaker::factory()->create(['name' => 'Packard']);
        $maker = CarMaker::factory()->create(['name' => 'Hudson']);
        $other = CarMaker::factory()->create(['name' => 'Nash']);

        $html = $this->asAdmin()->followingRedirects()
            ->patch(route('admin.makers.update', $maker), ['name' => 'packard'])
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression('/<dialog id="edit-maker-'.$maker->id.'"[^>]*data-dialog-autoopen/', $html);
        $this->assertSame(1, substr_count($html, 'data-dialog-autoopen='));
        $this->assertMatchesRegularExpression('/<input id="edit-maker-'.$maker->id.'-name" name="name" type="text"\s+value="packard"[^>]*aria-invalid="true" aria-describedby="edit-maker-'.$maker->id.'-name-hint edit-maker-'.$maker->id.'-name-error"/', $html);
        $this->assertStringContainsString('<p id="edit-maker-'.$maker->id.'-name-error" class="text-xs text-red-deep">The name has already been taken.</p>', $html);
        $this->assertMatchesRegularExpression('/<input id="edit-maker-'.$other->id.'-name" name="name" type="text"\s+value="Nash"/', $html);
        $this->assertMatchesRegularExpression('/<input id="name" name="name" type="text"\s+required/', $html);
        $this->assertStringNotContainsString('data-error-summary', $html);
    }

    public function test_a_failed_add_shows_the_summary_and_refills_only_the_add_form(): void
    {
        $maker = CarMaker::factory()->create(['name' => 'Packard']);

        $html = $this->asAdmin()->followingRedirects()
            ->post(route('admin.makers.store'), ['name' => 'packard'])
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-error-summary', $html);
        $this->assertStringContainsString('<a href="#name"', $html);
        $this->assertMatchesRegularExpression('/<input id="name" name="name" type="text"\s+value="packard"[^>]*aria-invalid="true"/', $html);
        $this->assertMatchesRegularExpression('/<input id="edit-maker-'.$maker->id.'-name" name="name" type="text"\s+value="Packard"/', $html);
        $this->assertStringNotContainsString('data-dialog-autoopen', $html);
    }

    public function test_deactivating_sets_only_the_flag_and_a_replay_is_harmless(): void
    {
        $maker = CarMaker::factory()->create(['name' => 'Hudson']);
        $model = CarModel::factory()->for($maker, 'maker')->create();
        $inactiveModel = CarModel::factory()->inactive()->for($maker, 'maker')->create();

        foreach ([1, 2] as $attempt) {
            $this->asAdmin()->patch(route('admin.makers.active', $maker), ['active' => '0'])
                ->assertRedirect(route('admin.makers'))
                ->assertSessionHas('success', 'Hudson is now inactive. It no longer appears in the listing form or the brand filters.');
        }

        $this->assertFalse($maker->refresh()->is_active);
        $this->assertSame('Hudson', $maker->name);
        $this->assertTrue($model->refresh()->is_active);
        $this->assertFalse($inactiveModel->refresh()->is_active);

        $this->asAdmin()->patch(route('admin.makers.active', $maker), ['active' => '1'])
            ->assertSessionHas('success', 'Hudson is active again.');

        $this->assertTrue($maker->refresh()->is_active);
        $this->assertTrue($model->refresh()->is_active);
        $this->assertFalse($inactiveModel->refresh()->is_active);
    }

    public function test_an_inactive_maker_leaves_the_brand_chips_and_the_create_form_until_reactivated(): void
    {
        $maker = CarMaker::factory()->create();
        $seller = User::factory()->create();

        $this->asAdmin()->patch(route('admin.makers.active', $maker), ['active' => '0']);

        $this->assertNotContains($maker->id, $this->get(route('listings.index'))->viewData('makers')->pluck('id')->all());
        $this->assertArrayNotHasKey($maker->id, $this->actingAs($seller)->get(route('listings.create'))->viewData('options')->makers);
        $this->app['auth']->forgetGuards();

        $this->asAdmin()->patch(route('admin.makers.active', $maker), ['active' => '1']);

        $this->assertContains($maker->id, $this->get(route('listings.index'))->viewData('makers')->pluck('id')->all());
        $this->assertArrayHasKey($maker->id, $this->actingAs($seller)->get(route('listings.create'))->viewData('options')->makers);
    }

    public function test_a_toggle_without_a_valid_state_returns_to_the_page_with_the_error(): void
    {
        $maker = CarMaker::factory()->create();

        $this->asAdmin()->patch(route('admin.makers.active', $maker), ['active' => 'maybe'])
            ->assertRedirect(route('admin.makers'))
            ->assertSessionHas('error', 'The status field must be true or false.')
            ->assertSessionHasNoErrors();

        $this->assertTrue($maker->refresh()->is_active);
    }

    public function test_an_unreferenced_maker_is_deleted(): void
    {
        $maker = CarMaker::factory()->create(['name' => 'Tucker']);

        $this->asAdmin()->delete(route('admin.makers.destroy', $maker))
            ->assertRedirect(route('admin.makers'))
            ->assertSessionHas('success', 'The maker Tucker has been deleted.');

        $this->assertModelMissing($maker);
    }

    public function test_deleting_a_maker_with_a_model_or_a_custom_listing_is_forbidden(): void
    {
        $withModel = CarMaker::factory()->create();
        CarModel::factory()->for($withModel, 'maker')->create();
        $withListing = CarMaker::factory()->create();
        CarListing::factory()->customModel()->for($withListing, 'maker')->create();

        $this->asAdmin()->delete(route('admin.makers.destroy', $withModel))->assertForbidden();
        $this->asAdmin()->delete(route('admin.makers.destroy', $withListing))->assertForbidden();

        $this->assertModelExists($withModel);
        $this->assertModelExists($withListing);
    }

    public function test_the_service_refuses_a_maker_that_gained_a_model_after_the_check(): void
    {
        $maker = CarMaker::factory()->create();
        $this->assertTrue($maker->isDeletable());
        CarModel::factory()->for($maker, 'maker')->create();

        $thrown = null;

        try {
            app(ReferenceDataService::class)->deleteMaker($maker);
        } catch (ReferenceDataInUseException $exception) {
            $thrown = $exception;
        }

        $this->assertInstanceOf(ReferenceDataInUseException::class, $thrown);
        $this->assertModelExists($maker);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function invalidWrites(): array
    {
        return [
            'store' => ['post', 'admin.makers.store'],
            'update' => ['patch', 'admin.makers.update'],
            'active' => ['patch', 'admin.makers.active'],
        ];
    }

    #[DataProvider('invalidWrites')]
    public function test_an_app_users_invalid_write_is_refused_before_validation(string $method, string $route): void
    {
        $maker = CarMaker::factory()->create(['name' => 'Hudson']);
        $url = $route === 'admin.makers.store' ? route($route) : route($route, $maker);

        $this->actingAs(User::factory()->create())->{$method}($url, ['name' => '', 'active' => 'maybe'])
            ->assertForbidden()
            ->assertSessionDoesntHaveErrors();

        $this->assertSame(['Hudson'], CarMaker::query()->pluck('name')->all());
    }

    public function test_an_unknown_or_non_numeric_maker_is_404(): void
    {
        $this->asAdmin()->patch('/admin/makers/999999', ['name' => 'Ghost'])->assertNotFound();
        $this->asAdmin()->get('/admin/makers/abc')->assertNotFound();
    }

    private function asAdmin(): static
    {
        $this->app['auth']->forgetGuards();

        return $this->actingAs(User::factory()->admin()->create());
    }
}
