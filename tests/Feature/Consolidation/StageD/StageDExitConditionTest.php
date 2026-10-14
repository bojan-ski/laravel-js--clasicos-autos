<?php

namespace Tests\Feature\Consolidation\StageD;

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
use App\Support\ListingFormValues;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StageDExitConditionTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const string NAME = 'Exit Bel Air Cincuenta y Siete';

    private const string REASON = 'The rear bumper photo is missing.';

    private User $seller;

    private User $admin;

    private CarMaker $maker;

    private CarModel $carModel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Storage::fake('public');

        $this->seller = User::factory()->create();
        $this->admin = User::factory()->admin()->create();
        $this->maker = CarMaker::factory()->create(['name' => 'Chevrolet']);
        $this->carModel = CarModel::factory()->for($this->maker, 'maker')->create(['name' => 'Bel Air', 'year_from' => 1950, 'year_to' => 1981]);
    }

    public function test_a_seller_lists_a_car_that_is_rejected_resubmitted_approved_re_photographed_and_deleted(): void
    {
        $this->actingAs($this->seller)->post(route('listings.store'), $this->payload())->assertRedirect();

        $listing = CarListing::query()->sole();
        $this->assertSame(ListingStatus::Pending, $listing->status);
        $this->assertSame(8, $listing->images()->count());
        $this->assertCount(24, $this->files($listing));
        $this->assertGuestGetsTheNotFoundPage($listing);

        $files = $this->files($listing);
        $this->actingAs($this->seller)
            ->post(route('listings.images.store', $listing), ['images' => [UploadedFile::fake()->image('ninth.jpg', 40, 30)]])
            ->assertRedirect(route('listings.images.edit', $listing))
            ->assertSessionHas('error', __('app.listings.images.limit_reached'));
        $this->assertSame(8, $listing->images()->count());
        $this->assertSame($files, $this->files($listing));

        $this->actingAs($this->admin)->get(route('listings.show', $listing))
            ->assertOk()
            ->assertSee('action="'.route('admin.listings.reject', $listing).'"', false);
        $this->actingAs($this->admin)
            ->patch(route('admin.listings.reject', $listing), ['version' => $listing->refresh()->moderationVersion(), 'rejection_reason' => self::REASON])
            ->assertRedirect(route('listings.show', $listing))
            ->assertSessionHas('success', __('app.listings.moderation.rejected'));
        $this->actingAs($this->seller)->get(route('profile'))
            ->assertOk()
            ->assertSee([self::NAME, ListingStatus::Rejected->label(), self::REASON]);
        $this->actingAs($this->seller)->get(route('listings.show', $listing))
            ->assertOk()
            ->assertSee([ListingStatus::Rejected->label(), __('app.listings.show.status.reason').' '.self::REASON]);
        $this->assertGuestGetsTheNotFoundPage($listing);

        $this->actingAs($this->seller)
            ->put(route('listings.update', $listing), [...ListingFormValues::for($listing->refresh()), 'description' => 'Two-tone paint, original interior, typo fixed.'])
            ->assertRedirect(route('listings.show', $listing));
        $listing->refresh();
        $this->assertSame(ListingStatus::Pending, $listing->status);
        $this->assertNull($listing->rejection_reason);

        $this->actingAs($this->admin)
            ->patch(route('admin.listings.approve', $listing), ['version' => $listing->moderationVersion()])
            ->assertRedirect(route('listings.show', $listing))
            ->assertSessionHas('success', __('app.listings.moderation.approved'));
        $this->asGuest()->get(route('listings.show', $listing))->assertOk()->assertSee(self::NAME);
        $this->asGuest()->get(route('listings.index'))->assertOk()->assertSee(self::NAME);

        $member = User::factory()->create();
        $this->actingAs($member)->post(route('bookmarks.store', $listing))->assertRedirect();
        $this->assertTrue($member->bookmarks()->whereKey($listing->id)->exists());

        $second = $listing->images()->where('position', 1)->sole();
        $this->actingAs($this->seller)
            ->patch(route('listings.images.primary', [$listing, $second]))
            ->assertRedirect(route('listings.images.edit', $listing));
        $this->assertSame(ListingStatus::Pending, $listing->refresh()->status);
        $this->assertSame(0, $second->refresh()->position);
        $this->assertGuestGetsTheNotFoundPage($listing);
        $this->asGuest()->get(route('listings.index'))->assertOk()->assertDontSee(self::NAME);

        $this->actingAs($this->seller)
            ->delete(route('listings.destroy', $listing))
            ->assertRedirect(route('profile'));
        $this->assertModelMissing($listing);
        $this->assertSame(0, ListingImage::query()->where('car_listing_id', $listing->id)->count());
        $this->assertSame(0, DB::table('user_bookmarks')->where('car_listing_id', $listing->id)->count());
        $this->assertFalse(Storage::disk('public')->directoryExists("listings/{$listing->id}"));
    }

    public function test_deleting_an_account_removes_its_listing_files_and_signs_the_request_out(): void
    {
        $leaving = User::factory()->create();
        $leavingListings = [$this->listingWithFiles($leaving), $this->listingWithFiles($leaving)];
        $staying = $this->listingWithFiles(User::factory()->create());
        $stayingFiles = $this->files($staying);
        $this->actingAs($leaving)->get(route('profile'))->assertOk();
        $tokenBefore = session()->token();

        $this->delete(route('profile.destroy'), ['delete_password' => 'password'])
            ->assertRedirect(route('home'));

        $this->assertGuest();
        $this->assertNotSame($tokenBefore, session()->token());
        $this->assertModelMissing($leaving);

        foreach ($leavingListings as $listing) {
            $this->assertFalse(Storage::disk('public')->directoryExists("listings/{$listing->id}"));
        }

        $this->assertCount(6, $stayingFiles);
        $this->assertSame($stayingFiles, $this->files($staying));
    }

    private function assertGuestGetsTheNotFoundPage(CarListing $listing): void
    {
        $this->asGuest()->get(route('listings.show', $listing))
            ->assertNotFound()
            ->assertSee(__('app.errors.404.title'));
    }

    private function asGuest(): static
    {
        $this->app['auth']->forgetGuards();

        return $this;
    }

    private function listingWithFiles(User $owner): CarListing
    {
        $listing = CarListing::factory()->approved()->for($owner, 'owner')->create();

        DB::transaction(fn () => app(ListingImageService::class)->storeForNewListing($listing, [
            UploadedFile::fake()->image('front.jpg', 40, 30),
            UploadedFile::fake()->image('back.jpg', 40, 30),
        ]));

        return $listing;
    }

    /**
     * @return list<string>
     */
    private function files(CarListing $listing): array
    {
        return Storage::disk('public')->allFiles("listings/{$listing->id}");
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        return [
            'name' => self::NAME,
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
            'description' => 'Two-tone paint, orignal interior.',
            'images' => array_map(fn (int $number): UploadedFile => UploadedFile::fake()->image("car-{$number}.jpg", 40, 30), range(1, 8)),
        ];
    }
}
