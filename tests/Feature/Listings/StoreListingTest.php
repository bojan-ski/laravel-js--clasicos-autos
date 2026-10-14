<?php

namespace Tests\Feature\Listings;

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
use App\Models\ListingImage;
use App\Models\User;
use App\Services\ListingImageService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class StoreListingTest extends TestCase
{
    use LazilyRefreshDatabase;

    private User $seller;

    private CarMaker $maker;

    private CarModel $carModel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Storage::fake('public');

        $this->seller = User::factory()->create();
        $this->maker = CarMaker::factory()->create(['name' => 'Chevrolet']);
        $this->carModel = CarModel::factory()->for($this->maker, 'maker')->create(['name' => 'Bel Air', 'year_from' => 1950, 'year_to' => 1981]);
    }

    public function test_a_valid_listing_is_stored_pending_with_its_photos_in_upload_order(): void
    {
        $this->freezeSecond();

        $response = $this->store($this->payload(images: [
            UploadedFile::fake()->image('a.jpg', 100, 50),
            UploadedFile::fake()->image('b.png', 60, 120),
            UploadedFile::fake()->image('c.jpg', 90, 90),
        ]));

        $listing = CarListing::query()->sole();
        $response->assertRedirect(route('listings.show', $listing))
            ->assertSessionHas('success', 'Car listing created successfully! It will be public once an admin approves it.');

        $this->assertTrue($listing->owner->is($this->seller));
        $this->assertSame(ListingStatus::Pending, $listing->status);
        $this->assertEquals(now(), $listing->submitted_at);
        $this->assertNull($listing->approved_at);
        $this->assertNull($listing->rejected_at);
        $this->assertNull($listing->rejection_reason);
        $this->assertNull($listing->moderated_by);
        $this->assertSame('1957 Bel Air', $listing->name);
        $this->assertSame(1957, $listing->year);
        $this->assertSame(25000, $listing->price);
        $this->assertSame(Transmission::Manual, $listing->transmission);
        $this->assertNull($listing->body_type);

        $images = $listing->images()->orderBy('position')->get();
        $this->assertSame([0, 1, 2], $images->pluck('position')->all());
        $this->assertSame([[100, 50], [60, 120], [90, 90]], $images->map(fn (ListingImage $image): array => $this->dimensions($image->path))->all());
        $images->each(function (ListingImage $image): void {
            Storage::disk('public')->assertExists([$image->path, $image->card_path, $image->thumb_path]);
        });
    }

    public function test_following_the_redirect_shows_the_owner_the_pending_status_panel(): void
    {
        $this->followingRedirects()->store($this->payload())
            ->assertOk()
            ->assertSee('Car listing created successfully!')
            ->assertSee('aria-label="'.__('app.listings.show.status.label').'"', false)
            ->assertSee(ListingStatus::Pending->label());
    }

    public function test_photos_posted_across_several_inputs_with_gaps_are_all_stored(): void
    {
        $this->store($this->payload(images: [
            0 => UploadedFile::fake()->image('desktop.jpg', 40, 40),
            3 => UploadedFile::fake()->image('library-1.jpg', 50, 50),
            4 => UploadedFile::fake()->image('library-2.jpg', 60, 60),
        ]))->assertSessionHasNoErrors();

        $listing = CarListing::query()->sole();
        $this->assertSame([0, 1, 2], $listing->images()->orderBy('position')->pluck('position')->all());
    }

    public function test_a_list_model_stores_its_id_and_copies_its_name_ignoring_typed_text(): void
    {
        $this->store($this->payload(['model' => 'Typed text']))->assertSessionHasNoErrors();

        $listing = CarListing::query()->sole();
        $this->assertSame($this->carModel->id, $listing->car_model_id);
        $this->assertSame('Bel Air', $listing->model);
    }

    public function test_other_stores_the_typed_model_without_a_model_id(): void
    {
        $this->store($this->payload(['car_model_id' => 'other', 'model' => 'Custom Special']))->assertSessionHasNoErrors();

        $listing = CarListing::query()->sole();
        $this->assertNull($listing->car_model_id);
        $this->assertSame('Custom Special', $listing->model);
        $this->assertTrue($listing->hasCustomModel());
    }

    public function test_other_without_typed_text_is_an_error_on_model(): void
    {
        $this->store($this->payload(['car_model_id' => 'other', 'model' => '']))
            ->assertRedirect(route('listings.create'))
            ->assertSessionHasErrors('model');

        $this->assertDatabaseCount('car_listings', 0);
    }

    public function test_a_model_of_another_maker_is_an_error_on_the_model(): void
    {
        $buick = CarMaker::factory()->create(['name' => 'Buick']);

        $this->store($this->payload(['car_maker_id' => $buick->id]))->assertSessionHasErrors('car_model_id');
        $this->assertDatabaseCount('car_listings', 0);
    }

    public function test_an_inactive_model_is_an_error_on_the_model(): void
    {
        $nomad = CarModel::factory()->for($this->maker, 'maker')->inactive()->create(['year_from' => 1950, 'year_to' => 1980]);

        $this->store($this->payload(['car_model_id' => $nomad->id]))->assertSessionHasErrors('car_model_id');
    }

    public function test_an_inactive_maker_is_an_error_on_the_maker(): void
    {
        $hudson = CarMaker::factory()->inactive()->create(['name' => 'Hudson']);
        $hornet = CarModel::factory()->for($hudson, 'maker')->create(['year_from' => 1950, 'year_to' => 1980]);

        $this->store($this->payload(['car_maker_id' => $hudson->id, 'car_model_id' => $hornet->id]))->assertSessionHasErrors('car_maker_id');
        $this->assertDatabaseCount('car_listings', 0);
    }

    /**
     * @return array<string, array{int}>
     */
    public static function yearsOutsideTheClassicRange(): array
    {
        return ['before 1900' => [1899], 'after the cutoff' => [1990]];
    }

    #[DataProvider('yearsOutsideTheClassicRange')]
    public function test_a_year_outside_the_classic_range_is_refused_with_only_the_range_error(int $year): void
    {
        $html = $this->followingRedirects()->store($this->payload(['year' => $year]))->getContent();

        $this->assertStringContainsString('<p id="year-error" class="text-xs text-red-deep">The Year field must be between 1900 and 1989.</p>', $html);
        $this->assertStringNotContainsString('was built', $html);
    }

    public function test_a_year_outside_the_models_production_years_names_them(): void
    {
        $impala = CarModel::factory()->for($this->maker, 'maker')->create(['name' => 'Impala', 'year_from' => 1958, 'year_to' => 1970]);

        $this->store($this->payload(['car_model_id' => $impala->id, 'year' => 1955]))
            ->assertSessionHasErrors(['year' => 'The Impala was built 1958–1970']);
        $this->assertDatabaseCount('car_listings', 0);
    }

    public function test_other_obeys_only_the_classic_range(): void
    {
        $this->store($this->payload(['car_model_id' => 'other', 'model' => 'One-off', 'year' => 1901]))->assertSessionHasNoErrors();

        $this->assertDatabaseCount('car_listings', 1);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function refusedPhotoSets(): array
    {
        return [
            'none' => ['none', 'images'],
            'nine' => ['nine', 'images'],
            'a pdf' => ['pdf', 'images.0'],
            'a gif' => ['gif', 'images.0'],
            'an 11 MB jpeg' => ['large', 'images.0'],
        ];
    }

    #[DataProvider('refusedPhotoSets')]
    public function test_a_refused_photo_set_stores_nothing(string $set, string $errorKey): void
    {
        $images = match ($set) {
            'none' => [],
            'nine' => array_map(fn (int $index): UploadedFile => UploadedFile::fake()->image("{$index}.jpg", 20, 20), range(1, 9)),
            'pdf' => [UploadedFile::fake()->create('papers.pdf', 100, 'application/pdf')],
            'gif' => [UploadedFile::fake()->image('car.gif', 20, 20)],
            'large' => [UploadedFile::fake()->image('car.jpg', 20, 20)->size(11 * 1024)],
        };

        $this->store($this->payload(images: $images))->assertSessionHasErrors($errorKey);

        $this->assertDatabaseCount('car_listings', 0);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    /**
     * @return array<string, array{string, mixed}>
     */
    public static function refusedValues(): array
    {
        return [
            'a decimal price' => ['price', '12500.50'],
            'a price with a comma' => ['price', '12,500'],
            'a negative price' => ['price', -1],
            'a price over the cap' => ['price', 4294967296],
            'a negative mileage' => ['mileage', -1],
            'a mileage over the cap' => ['mileage', 4294967296],
            '101 % original parts' => ['original_parts_percentage', 101],
            'an unknown enum value' => ['transmission', 'hovercraft'],
            'an unknown optional enum value' => ['body_type', 'zeppelin'],
            'a missing description' => ['description', ''],
            'a description over 5000 characters' => ['description', str_repeat('a', 5001)],
        ];
    }

    #[DataProvider('refusedValues')]
    public function test_a_refused_value_is_an_error_on_its_field(string $field, mixed $value): void
    {
        $this->store($this->payload([$field => $value]))->assertSessionHasErrors($field);

        $this->assertDatabaseCount('car_listings', 0);
    }

    public function test_the_upper_bounds_themselves_are_accepted(): void
    {
        $this->store($this->payload(['price' => 4294967295, 'mileage' => 4294967295, 'original_parts_percentage' => 100]))->assertSessionHasNoErrors();

        $this->assertSame(4294967295, CarListing::query()->sole()->price);
    }

    public function test_after_a_failure_the_form_refills_and_shows_the_summary_and_the_reselect_notice(): void
    {
        $response = $this->followingRedirects()->store($this->payload(['name' => '', 'engine_type' => 'Straight-six', 'transmission' => Transmission::Automatic->value]));

        $html = preg_replace('/\s+>/', '>', $response->getContent());
        $this->assertStringContainsString('role="alert"', $html);
        $this->assertStringContainsString('The Name field is required.', $html);
        $this->assertStringContainsString('<p id="name-error" class="text-xs text-red-deep">The Name field is required.</p>', $html);
        $this->assertStringContainsString('value="Straight-six"', $html);
        $this->assertStringContainsString('<option value="'.Transmission::Automatic->value.'" selected>', $html);
        $this->assertStringContainsString('<option value="'.$this->carModel->id.'" selected>', $html);
        $this->assertStringContainsString(__('app.listings.form.photos.reselect'), $html);
    }

    public function test_a_photo_error_marks_the_photo_inputs_invalid(): void
    {
        $html = $this->followingRedirects()->store($this->payload(images: []))->getContent();

        $this->assertSame(3, preg_match_all('/<input id="images-[a-z]+"[^>]*aria-invalid="true"/', $html));
        $this->assertStringContainsString('<p id="images-error"', $html);
    }

    public function test_server_controlled_fields_in_the_request_are_ignored(): void
    {
        $other = User::factory()->create();

        $this->store($this->payload([
            'status' => 'approved',
            'user_id' => $other->id,
            'submitted_at' => '2001-01-01 00:00:00',
            'approved_at' => '2001-01-01 00:00:00',
            'rejected_at' => '2001-01-01 00:00:00',
            'rejection_reason' => 'Injected',
            'moderated_by' => $other->id,
        ]))->assertSessionHasNoErrors();

        $listing = CarListing::query()->sole();
        $this->assertSame(ListingStatus::Pending, $listing->status);
        $this->assertSame($this->seller->id, $listing->user_id);
        $this->assertTrue($listing->submitted_at->isToday());
        $this->assertNull($listing->approved_at);
        $this->assertNull($listing->rejected_at);
        $this->assertNull($listing->rejection_reason);
        $this->assertNull($listing->moderated_by);
    }

    public function test_creating_a_listing_sends_no_notification_and_dispatches_no_broadcast(): void
    {
        Notification::fake();
        Queue::fake();

        $this->store($this->payload())->assertSessionHasNoErrors();

        Notification::assertNothingSent();
        Queue::assertNothingPushed();
    }

    public function test_validation_messages_follow_the_form_order(): void
    {
        $html = $this->followingRedirects()->store(['car_model_id' => ''])->getContent();

        preg_match('/role="alert".*?<\/ul>/s', $html, $summary);
        preg_match_all('/<li>The (.+?) field is required\.<\/li>/', $summary[0], $labels);

        $this->assertSame(['Name', 'Car Maker', 'Model', 'Price', 'Year', 'Mileage', 'Transmission', 'Fuel type'], array_slice($labels[1], 0, 8));
        $this->assertSame('Car description', $labels[1][count($labels[1]) - 2]);
    }

    public function test_an_image_failure_after_the_insert_rolls_the_listing_back(): void
    {
        $this->mock(ListingImageService::class)
            ->shouldReceive('storeForNewListing')
            ->once()
            ->andThrow(new RuntimeException('Disk full.'));

        $this->store($this->payload())->assertInternalServerError();

        $this->assertDatabaseCount('car_listings', 0);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @param  array<int, UploadedFile>|null  $images
     * @return array<string, mixed>
     */
    private function payload(array $overrides = [], ?array $images = null): array
    {
        return [
            'name' => '1957 Bel Air',
            'car_maker_id' => $this->maker->id,
            'car_model_id' => $this->carModel->id,
            'price' => 25000,
            'year' => 1957,
            'mileage' => 120000,
            'transmission' => Transmission::Manual->value,
            'fuel_type' => FuelType::Gasoline->value,
            'engine_size' => '4.6L',
            'engine_type' => 'V8',
            'engine_history' => EngineHistory::cases()[0]->value,
            'engine_condition' => EngineCondition::cases()[0]->value,
            'restoration_history' => RestorationHistory::cases()[0]->value,
            'original_parts_percentage' => 80,
            'odometer' => Odometer::cases()[0]->value,
            'documentation_status' => DocumentationStatus::cases()[0]->value,
            'location_city' => 'Havana',
            'location_state' => 'La Habana',
            'location_zipcode' => '10400',
            'description' => 'Two-tone paint, original interior.',
            'images' => $images ?? [UploadedFile::fake()->image('car.jpg', 40, 30)],
            ...$overrides,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function store(array $payload): TestResponse
    {
        return $this->actingAs($this->seller)->post(route('listings.store'), $payload);
    }

    /**
     * @return array{int, int}
     */
    private function dimensions(string $path): array
    {
        [$width, $height] = getimagesizefromstring(Storage::disk('public')->get($path));

        return [$width, $height];
    }
}
