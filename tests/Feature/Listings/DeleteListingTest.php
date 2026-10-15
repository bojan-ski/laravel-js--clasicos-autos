<?php

namespace Tests\Feature\Listings;

use App\Models\CarListing;
use App\Models\User;
use App\Services\ListingService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class DeleteListingTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Storage::fake('public');
    }

    public function test_a_guest_is_sent_to_login(): void
    {
        $listing = CarListing::factory()->approved()->create();

        $this->delete(route('listings.destroy', $listing))->assertRedirect(route('login'));

        $this->assertModelExists($listing);
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
    public function test_a_stranger_is_refused_and_nothing_is_deleted(string $state, int $status): void
    {
        $listing = match ($state) {
            'approved' => CarListing::factory()->approved()->create(),
            'pending' => CarListing::factory()->create(),
            'rejected' => CarListing::factory()->rejected()->create(),
            'suspended owner' => CarListing::factory()->approved()->for(User::factory()->suspended(), 'owner')->create(),
        };
        $this->putFiles($listing);

        $this->actingAs(User::factory()->create())->delete(route('listings.destroy', $listing))->assertStatus($status);

        $this->assertModelExists($listing);
        Storage::disk('public')->assertExists("listings/{$listing->id}/photo.webp");
    }

    public function test_the_owner_is_redirected_to_the_profile_with_the_flash(): void
    {
        $listing = CarListing::factory()->rejected()->create();

        $this->actingAs($listing->owner)->delete(route('listings.destroy', $listing))
            ->assertRedirect(route('profile'))
            ->assertSessionHas('success', 'Car listing deleted.');

        $this->assertModelMissing($listing);
    }

    public function test_a_suspended_owner_in_a_live_session_is_signed_out_and_deletes_nothing(): void
    {
        $listing = CarListing::factory()->approved()->for(User::factory()->suspended(), 'owner')->create();
        $this->putFiles($listing);

        $this->actingAs($listing->owner)->delete(route('listings.destroy', $listing))->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertModelExists($listing);
        Storage::disk('public')->assertExists("listings/{$listing->id}/photo.webp");
    }

    public function test_an_admin_is_redirected_to_the_listings_index_with_the_flash(): void
    {
        $listing = CarListing::factory()->create();

        $this->actingAs(User::factory()->admin()->create())->delete(route('listings.destroy', $listing))
            ->assertRedirect(route('listings.index'))
            ->assertSessionHas('success', 'Car listing deleted.');

        $this->assertModelMissing($listing);
    }

    public function test_deleting_removes_the_image_rows_bookmarks_and_directory_but_not_another_listings(): void
    {
        $listing = CarListing::factory()->approved()->withImages(3)->create();
        $other = CarListing::factory()->approved()->withImages(1)->create();
        $bookmarker = User::factory()->create();
        $bookmarker->bookmarks()->attach([$listing->id, $other->id]);
        $this->putFiles($listing);
        $this->putFiles($other);

        $this->actingAs($listing->owner)->delete(route('listings.destroy', $listing))->assertRedirect(route('profile'));

        $this->assertModelMissing($listing);
        $this->assertDatabaseMissing('listing_images', ['car_listing_id' => $listing->id]);
        $this->assertDatabaseMissing('user_bookmarks', ['car_listing_id' => $listing->id]);
        Storage::disk('public')->assertMissing("listings/{$listing->id}");

        $this->assertDatabaseCount('listing_images', 1);
        $this->assertDatabaseHas('user_bookmarks', ['car_listing_id' => $other->id]);
        Storage::disk('public')->assertExists("listings/{$other->id}/photo.webp");
    }

    public function test_a_failed_row_delete_leaves_the_files_in_place(): void
    {
        $listing = CarListing::factory()->approved()->withImages(1)->create();
        $this->putFiles($listing);
        CarListing::deleted(fn () => throw new RuntimeException('Delete failed.'));
        $this->withoutExceptionHandling();
        $exception = null;

        try {
            $this->actingAs($listing->owner)->delete(route('listings.destroy', $listing));
        } catch (RuntimeException $caught) {
            $exception = $caught;
        }

        $this->assertSame('Delete failed.', $exception?->getMessage());
        $this->assertModelExists($listing);
        Storage::disk('public')->assertExists("listings/{$listing->id}/photo.webp");
    }

    public function test_the_directory_is_removed_only_after_the_commit(): void
    {
        $listing = CarListing::factory()->approved()->create();
        $this->putFiles($listing);
        $exception = null;

        try {
            DB::transaction(function () use ($listing): void {
                app(ListingService::class)->delete($listing);

                throw new RuntimeException('Caller rolled back.');
            });
        } catch (RuntimeException $caught) {
            $exception = $caught;
        }

        $this->assertSame('Caller rolled back.', $exception?->getMessage());
        $this->assertModelExists($listing);
        Storage::disk('public')->assertExists("listings/{$listing->id}/photo.webp");
    }

    private function putFiles(CarListing $listing): void
    {
        Storage::disk('public')->put("listings/{$listing->id}/photo.webp", 'x');
    }
}
