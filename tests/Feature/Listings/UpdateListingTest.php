<?php

namespace Tests\Feature\Listings;

use App\Enums\ListingStatus;
use App\Models\CarListing;
use App\Models\CarMaker;
use App\Models\CarModel;
use App\Models\ListingImage;
use App\Models\User;
use App\Services\ListingService;
use App\Support\ListingFormValues;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class UpdateListingTest extends TestCase
{
    use LazilyRefreshDatabase;

    private CarMaker $maker;

    private CarModel $belAir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Storage::fake('public');

        $this->maker = CarMaker::factory()->create(['name' => 'Chevrolet']);
        $this->belAir = CarModel::factory()->for($this->maker, 'maker')->create(['name' => 'Bel Air', 'year_from' => 1950, 'year_to' => 1981]);
    }

    public function test_a_guest_is_sent_to_login(): void
    {
        $listing = CarListing::factory()->approved()->create();

        $this->put(route('listings.update', $listing), [])->assertRedirect(route('login'));
    }

    /**
     * @return array<string, array{string, int}>
     */
    public static function refusedStrangers(): array
    {
        return [
            'public listing' => ['approved', 403],
            'pending listing' => ['pending', 404],
            'rejected listing' => ['rejected', 404],
            'suspended owner' => ['suspended owner', 404],
        ];
    }

    #[DataProvider('refusedStrangers')]
    public function test_a_stranger_is_refused_before_validation(string $state, int $status): void
    {
        $listing = match ($state) {
            'approved' => CarListing::factory()->approved()->create(['name' => 'Untouched']),
            'pending' => CarListing::factory()->create(['name' => 'Untouched']),
            'rejected' => CarListing::factory()->rejected()->create(['name' => 'Untouched']),
            'suspended owner' => CarListing::factory()->approved()->for(User::factory()->suspended(), 'owner')->create(['name' => 'Untouched']),
        };

        $this->actingAs(User::factory()->create())->put(route('listings.update', $listing), ['name' => ''])
            ->assertStatus($status)
            ->assertSessionHasNoErrors();

        $this->assertSame('Untouched', $listing->fresh()->name);
    }

    public function test_a_valid_save_redirects_to_the_detail_page_with_the_flash(): void
    {
        $listing = $this->listing();

        $this->save($listing, ['name' => 'Renamed Bel Air'])
            ->assertRedirect(route('listings.show', $listing))
            ->assertSessionHas('success', 'Car listing updated successfully.');

        $this->assertSame('Renamed Bel Air', $listing->fresh()->name);
    }

    public function test_a_failed_save_redirects_to_the_edit_page_with_the_old_input(): void
    {
        $listing = $this->listing(ListingStatus::Approved);

        $this->save($listing, ['name' => 'Posted name', 'price' => 'abc'], headers: ['Referer' => route('listings.show', $listing)])
            ->assertRedirect(route('listings.edit', $listing))
            ->assertSessionHasErrors('price')
            ->assertSessionHasInput('name', 'Posted name');

        $fresh = $listing->fresh();
        $this->assertSame(ListingStatus::Approved, $fresh->status);
        $this->assertNotSame('Posted name', $fresh->name);
    }

    /**
     * @return array<string, array{ListingStatus}>
     */
    public static function moderatedStatuses(): array
    {
        return ['approved' => [ListingStatus::Approved], 'rejected' => [ListingStatus::Rejected]];
    }

    #[DataProvider('moderatedStatuses')]
    public function test_an_unchanged_save_returns_a_moderated_listing_to_pending(ListingStatus $status): void
    {
        $listing = $this->listing($status, ['submitted_at' => now()->subWeek()]);
        $moderator = $listing->moderated_by;
        $this->freezeSecond();

        $this->save($listing)->assertSessionHasNoErrors();

        $fresh = $listing->fresh();
        $this->assertSame(ListingStatus::Pending, $fresh->status);
        $this->assertEquals(now(), $fresh->submitted_at);
        $this->assertNull($fresh->approved_at);
        $this->assertNull($fresh->rejected_at);
        $this->assertNull($fresh->rejection_reason);
        $this->assertSame($moderator, $fresh->moderated_by);
        $this->assertNotNull($moderator);
    }

    public function test_a_pending_listing_stays_pending_without_a_new_submission_time(): void
    {
        $submitted = now()->subWeek()->startOfSecond();
        $listing = $this->listing(ListingStatus::Pending, ['submitted_at' => $submitted]);

        $this->save($listing, ['name' => 'Renamed'])->assertSessionHasNoErrors();

        $fresh = $listing->fresh();
        $this->assertSame(ListingStatus::Pending, $fresh->status);
        $this->assertEquals($submitted, $fresh->submitted_at);
    }

    public function test_an_admins_save_of_an_approved_listing_makes_it_pending(): void
    {
        $listing = $this->listing(ListingStatus::Approved);

        $this->save($listing, ['name' => 'Admin fix'], User::factory()->admin()->create())
            ->assertRedirect(route('listings.show', $listing));

        $fresh = $listing->fresh();
        $this->assertSame(ListingStatus::Pending, $fresh->status);
        $this->assertSame('Admin fix', $fresh->name);
    }

    /**
     * @return array<string, array{ListingStatus, bool}>
     */
    public static function nonPublicListingsForAdmins(): array
    {
        return [
            'pending' => [ListingStatus::Pending, false],
            'rejected' => [ListingStatus::Rejected, false],
            'suspended owner' => [ListingStatus::Approved, true],
        ];
    }

    #[DataProvider('nonPublicListingsForAdmins')]
    public function test_an_admin_can_save_a_non_public_listing(ListingStatus $status, bool $suspendedOwner): void
    {
        $owner = $suspendedOwner ? User::factory()->suspended()->create() : null;
        $listing = $this->listing($status, owner: $owner);

        $this->save($listing, ['name' => 'Admin fix'], User::factory()->admin()->create())
            ->assertRedirect(route('listings.show', $listing))
            ->assertSessionHasNoErrors();

        $this->assertSame('Admin fix', $listing->fresh()->name);
    }

    public function test_a_suspended_owner_in_a_live_session_can_save(): void
    {
        $listing = $this->listing(ListingStatus::Approved, owner: User::factory()->suspended()->create());

        $this->save($listing, ['name' => 'Saved while suspended'])->assertSessionHasNoErrors();

        $this->assertSame('Saved while suspended', $listing->fresh()->name);
    }

    public function test_the_listings_inactive_current_maker_and_model_are_accepted_unchanged(): void
    {
        $hudson = CarMaker::factory()->inactive()->create();
        $hornet = CarModel::factory()->for($hudson, 'maker')->inactive()->create(['year_from' => 1951, 'year_to' => 1957]);
        $listing = CarListing::factory()->approved()->forCarModel($hornet)->create();

        $this->save($listing)->assertSessionHasNoErrors();

        $this->assertSame($hornet->id, $listing->fresh()->car_model_id);
    }

    public function test_an_active_maker_with_its_inactive_current_model_is_accepted(): void
    {
        $nomad = CarModel::factory()->for($this->maker, 'maker')->inactive()->create(['name' => 'Nomad', 'year_from' => 1955, 'year_to' => 1961]);
        $listing = CarListing::factory()->forCarModel($nomad)->create();

        $this->save($listing, ['name' => 'Still a Nomad'])->assertSessionHasNoErrors();

        $this->assertSame('Nomad', $listing->fresh()->model);
    }

    public function test_another_inactive_model_of_the_same_maker_is_refused_on_the_model(): void
    {
        $nomad = CarModel::factory()->for($this->maker, 'maker')->inactive()->create(['year_from' => 1950, 'year_to' => 1981]);
        $listing = $this->listing();

        $this->save($listing, ['car_model_id' => $nomad->id])->assertSessionHasErrors('car_model_id');

        $this->assertSame($this->belAir->id, $listing->fresh()->car_model_id);
    }

    public function test_the_current_model_under_another_maker_is_refused_on_the_model(): void
    {
        $buick = CarMaker::factory()->create(['name' => 'Buick']);
        $listing = $this->listing();

        $this->save($listing, ['car_maker_id' => $buick->id, 'car_model_id' => $this->belAir->id])
            ->assertSessionHasErrors('car_model_id')
            ->assertSessionDoesntHaveErrors('car_maker_id');
    }

    public function test_an_inactive_maker_other_than_the_current_one_is_refused_on_the_maker(): void
    {
        $packard = CarMaker::factory()->inactive()->create();
        $clipper = CarModel::factory()->for($packard, 'maker')->create(['year_from' => 1950, 'year_to' => 1981]);
        $listing = $this->listing();

        $this->save($listing, ['car_maker_id' => $packard->id, 'car_model_id' => $clipper->id])->assertSessionHasErrors('car_maker_id');
    }

    public function test_a_year_outside_the_chosen_models_production_years_is_refused(): void
    {
        $listing = $this->listing();

        $this->save($listing, ['year' => 1985])->assertSessionHasErrors(['year' => 'The Bel Air was built 1950–1981']);
    }

    public function test_the_production_years_also_bind_an_inactive_current_model(): void
    {
        $hornet = CarModel::factory()->for($this->maker, 'maker')->inactive()->create(['name' => 'Hornet', 'year_from' => 1951, 'year_to' => 1957]);
        $listing = CarListing::factory()->forCarModel($hornet)->create();

        $this->save($listing, ['year' => 1960])->assertSessionHasErrors(['year' => 'The Hornet was built 1951–1957']);
    }

    public function test_other_without_typed_text_is_refused_on_the_model(): void
    {
        $listing = $this->listing();

        $this->save($listing, ['car_model_id' => 'other', 'model' => ''])->assertSessionHasErrors('model');
    }

    public function test_a_list_model_copies_its_name_and_ignores_typed_text(): void
    {
        $impala = CarModel::factory()->for($this->maker, 'maker')->create(['name' => 'Impala', 'year_from' => 1958, 'year_to' => 1985]);
        $listing = CarListing::factory()->customModel()->create(['car_maker_id' => $this->maker->id, 'year' => 1960]);

        $this->save($listing, ['car_model_id' => $impala->id, 'model' => 'Typed text'])->assertSessionHasNoErrors();

        $fresh = $listing->fresh();
        $this->assertSame($impala->id, $fresh->car_model_id);
        $this->assertSame('Impala', $fresh->model);
    }

    public function test_a_custom_model_listing_cannot_switch_to_an_inactive_model(): void
    {
        $nomad = CarModel::factory()->for($this->maker, 'maker')->inactive()->create(['name' => 'Nomad', 'year_from' => 1955, 'year_to' => 1961]);
        $listing = CarListing::factory()->customModel()->create(['car_maker_id' => $this->maker->id, 'model' => 'Custom Special', 'year' => 1960]);

        $this->save($listing, ['car_model_id' => $nomad->id])->assertSessionHasErrors('car_model_id');

        $fresh = $listing->fresh();
        $this->assertNull($fresh->car_model_id);
        $this->assertSame('Custom Special', $fresh->model);
    }

    public function test_switching_to_other_stores_the_typed_model_without_a_model_id(): void
    {
        $listing = $this->listing();

        $this->save($listing, ['car_model_id' => 'other', 'model' => 'Custom Special'])->assertSessionHasNoErrors();

        $fresh = $listing->fresh();
        $this->assertNull($fresh->car_model_id);
        $this->assertSame('Custom Special', $fresh->model);
    }

    public function test_posted_server_fields_are_ignored(): void
    {
        $listing = $this->listing(ListingStatus::Rejected);
        $original = $listing->only(['user_id', 'moderated_by']);
        $this->freezeSecond();

        $this->save($listing, [
            'status' => 'approved',
            'user_id' => User::factory()->create()->id,
            'submitted_at' => '2000-01-01 00:00:00',
            'approved_at' => '2000-01-01 00:00:00',
            'rejected_at' => '2000-01-01 00:00:00',
            'rejection_reason' => 'Posted reason',
            'moderated_by' => User::factory()->admin()->create()->id,
        ])->assertSessionHasNoErrors();

        $fresh = $listing->fresh();
        $this->assertSame(ListingStatus::Pending, $fresh->status);
        $this->assertSame($original, $fresh->only(['user_id', 'moderated_by']));
        $this->assertEquals(now(), $fresh->submitted_at);
        $this->assertNull($fresh->approved_at);
        $this->assertNull($fresh->rejected_at);
        $this->assertNull($fresh->rejection_reason);
    }

    public function test_a_save_racing_a_delete_is_not_found_and_inserts_nothing(): void
    {
        $listing = $this->listing();
        $data = [...ListingFormValues::for($listing), 'car_model_id' => $this->belAir->id];
        unset($data['model']);
        CarListing::query()->whereKey($listing->id)->delete();

        $this->expectException(ModelNotFoundException::class);

        try {
            app(ListingService::class)->update($listing, $data);
        } finally {
            $this->assertDatabaseCount('car_listings', 0);
        }
    }

    public function test_a_save_leaves_the_images_rows_and_files_untouched(): void
    {
        $listing = CarListing::factory()->approved()->forCarModel($this->belAir)->withImages(2)->create();
        $images = $listing->images()->get();
        $images->each(fn (ListingImage $image) => Storage::disk('public')->put($image->path, 'x'));
        $before = $images->map->only(['id', 'path', 'card_path', 'thumb_path', 'position'])->all();

        $this->save($listing, ['name' => 'Renamed'])->assertSessionHasNoErrors();

        $this->assertSame($before, $listing->images()->get()->map->only(['id', 'path', 'card_path', 'thumb_path', 'position'])->all());
        $images->each(fn (ListingImage $image) => Storage::disk('public')->assertExists($image->path));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function listing(ListingStatus $status = ListingStatus::Approved, array $attributes = [], ?User $owner = null): CarListing
    {
        $factory = match ($status) {
            ListingStatus::Approved => CarListing::factory()->approved(),
            ListingStatus::Rejected => CarListing::factory()->rejected(),
            ListingStatus::Pending => CarListing::factory(),
        };

        return $factory->forCarModel($this->belAir)->for($owner ?? User::factory(), 'owner')->create(['year' => 1957, ...$attributes]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @param  array<string, string>  $headers
     */
    private function save(CarListing $listing, array $overrides = [], ?User $actor = null, array $headers = []): TestResponse
    {
        return $this->actingAs($actor ?? $listing->owner)
            ->withHeaders($headers)
            ->put(route('listings.update', $listing), [...ListingFormValues::for($listing), ...$overrides]);
    }
}
