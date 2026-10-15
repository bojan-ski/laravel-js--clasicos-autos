<?php

namespace Tests\Feature\Listings;

use App\Enums\ListingStatus;
use App\Models\CarListing;
use App\Models\ListingImage;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ManageListingImagesTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Storage::fake('public');
    }

    /**
     * @return array<string, array{string}>
     */
    public static function writeRoutes(): array
    {
        return [
            'store' => ['listings.images.store'],
            'primary' => ['listings.images.primary'],
            'destroy' => ['listings.images.destroy'],
        ];
    }

    #[DataProvider('writeRoutes')]
    public function test_a_guest_is_sent_to_login(string $route): void
    {
        $listing = CarListing::factory()->approved()->withImages(2)->create();
        $before = $this->snapshot($listing);

        $this->write($route, $listing)->assertRedirect(route('login'));

        $this->assertSame($before, $this->snapshot($listing));
    }

    /**
     * @return array<string, array{string, string, int}>
     */
    public static function refusedStrangers(): array
    {
        $cases = [];

        foreach (['listings.images.store', 'listings.images.primary', 'listings.images.destroy'] as $route) {
            foreach (['approved' => 403, 'pending' => 404, 'rejected' => 404, 'suspended owner' => 404] as $state => $status) {
                $cases["{$route}, {$state}"] = [$route, $state, $status];
            }
        }

        return $cases;
    }

    #[DataProvider('refusedStrangers')]
    public function test_a_stranger_is_refused_before_validation_and_nothing_changes(string $route, string $state, int $status): void
    {
        $factory = match ($state) {
            'approved' => CarListing::factory()->approved(),
            'pending' => CarListing::factory(),
            'rejected' => CarListing::factory()->rejected(),
            'suspended owner' => CarListing::factory()->approved()->for(User::factory()->suspended(), 'owner'),
        };
        $listing = $factory->withImages(2)->create();
        $before = $this->snapshot($listing);

        $this->actingAs(User::factory()->create());
        $this->write($route, $listing)
            ->assertStatus($status)
            ->assertSessionDoesntHaveErrors();

        $this->assertSame($before, $this->snapshot($listing));
    }

    /**
     * @return array<string, array{string, int}>
     */
    public static function refusedInvalidAdds(): array
    {
        return ['public listing' => ['approved', 403], 'pending listing' => ['pending', 404]];
    }

    #[DataProvider('refusedInvalidAdds')]
    public function test_a_strangers_invalid_add_is_refused_before_validation(string $state, int $status): void
    {
        $listing = ($state === 'approved' ? CarListing::factory()->approved() : CarListing::factory())->withImages(1)->create();

        $this->actingAs(User::factory()->create())
            ->post(route('listings.images.store', $listing), ['images' => [UploadedFile::fake()->create('notes.gif', 1, 'image/gif')]])
            ->assertStatus($status)
            ->assertSessionDoesntHaveErrors();

        $this->actingAs(User::factory()->create())
            ->post(route('listings.images.store', $listing))
            ->assertStatus($status)
            ->assertSessionDoesntHaveErrors();
    }

    /**
     * @return array<string, array{string}>
     */
    public static function imageRoutes(): array
    {
        return ['primary' => ['listings.images.primary'], 'destroy' => ['listings.images.destroy']];
    }

    #[DataProvider('imageRoutes')]
    public function test_an_image_of_another_listing_is_a_404_for_the_owner(string $route): void
    {
        $listing = CarListing::factory()->approved()->withImages(2)->create();
        $foreign = CarListing::factory()->approved()->withImages(2)->create()->images()->where('position', 1)->sole();

        $this->actingAs($listing->owner)
            ->call($route === 'listings.images.primary' ? 'PATCH' : 'DELETE', route($route, [$listing, $foreign]))
            ->assertNotFound();

        $this->assertModelExists($foreign);
        $this->assertSame(1, $foreign->fresh()->position);
        $this->assertSame(ListingStatus::Approved, $listing->fresh()->status);
    }

    #[DataProvider('imageRoutes')]
    public function test_an_unknown_image_is_a_404(string $route): void
    {
        $listing = CarListing::factory()->approved()->withImages(2)->create();

        $this->actingAs($listing->owner)
            ->call($route === 'listings.images.primary' ? 'PATCH' : 'DELETE', route($route, [$listing, 999999]))
            ->assertNotFound();
    }

    #[DataProvider('imageRoutes')]
    public function test_a_foreign_image_is_a_404_even_for_a_viewer_the_policy_would_refuse_with_403(string $route): void
    {
        $listing = CarListing::factory()->approved()->withImages(2)->create();
        $foreign = CarListing::factory()->approved()->withImages(2)->create()->images()->where('position', 1)->sole();

        $this->actingAs(User::factory()->create())
            ->call($route === 'listings.images.primary' ? 'PATCH' : 'DELETE', route($route, [$listing, $foreign]))
            ->assertNotFound();
    }

    public function test_adding_appends_the_batch_after_the_last_position_with_three_files_each(): void
    {
        $listing = CarListing::factory()->withImages(5)->create();

        $this->actingAs($listing->owner)
            ->post(route('listings.images.store', $listing), ['images' => [$this->photo('a.jpg'), $this->photo('b.png')]])
            ->assertRedirect(route('listings.images.edit', $listing))
            ->assertSessionHas('success', 'Images gallery updated');

        $added = $listing->images()->where('position', '>=', 5)->get();
        $this->assertSame([5, 6], $added->pluck('position')->all());
        $this->assertSame(7, $listing->images()->count());

        foreach ($added as $image) {
            Storage::disk('public')->assertExists([$image->path, $image->card_path, $image->thumb_path]);
        }
    }

    /**
     * @return array<string, array{int, int, string}>
     */
    public static function limitRefusals(): array
    {
        return [
            'at the limit' => [8, 1, 'The limit is 8 images!'],
            'batch would exceed' => [6, 3, 'Adding new images will exceed the limit of 8 images!'],
        ];
    }

    #[DataProvider('limitRefusals')]
    public function test_the_limit_refusals_are_flashed_as_errors_and_write_nothing(int $existing, int $batch, string $message): void
    {
        $listing = CarListing::factory()->approved()->withImages($existing)->create();
        $before = $this->snapshot($listing);
        $files = array_map(fn (int $index): UploadedFile => $this->photo("photo{$index}.jpg"), range(1, $batch));

        $this->actingAs($listing->owner)
            ->post(route('listings.images.store', $listing), ['images' => $files])
            ->assertRedirect(route('listings.images.edit', $listing))
            ->assertSessionHas('error', $message)
            ->assertSessionMissing('success')
            ->assertSessionHasNoErrors();

        $this->assertSame($before, $this->snapshot($listing));
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function invalidBatches(): array
    {
        return [
            'no files' => ['none', 'images'],
            'eight files' => ['eight', 'images'],
            'a non-image' => ['pdf', 'images.0'],
            'an over-size file' => ['huge', 'images.0'],
        ];
    }

    #[DataProvider('invalidBatches')]
    public function test_an_invalid_batch_redirects_to_the_images_page_with_an_error(string $batch, string $field): void
    {
        $listing = CarListing::factory()->approved()->withImages(1)->create();
        $before = $this->snapshot($listing);
        $images = match ($batch) {
            'none' => [],
            'eight' => array_map(fn (int $index): UploadedFile => $this->photo("photo{$index}.jpg"), range(1, 8)),
            'pdf' => [UploadedFile::fake()->create('papers.pdf', 10, 'application/pdf')],
            'huge' => [$this->photo('huge.jpg')->size(10241)],
        };

        $this->actingAs($listing->owner)
            ->from(route('listings.show', $listing))
            ->post(route('listings.images.store', $listing), ['images' => $images])
            ->assertRedirect(route('listings.images.edit', $listing))
            ->assertSessionHasErrors($field);

        $this->assertSame($before, $this->snapshot($listing));
    }

    public function test_validation_messages_name_the_visible_label(): void
    {
        $listing = CarListing::factory()->withImages(1)->create();
        $images = array_map(fn (int $index): UploadedFile => $this->photo("photo{$index}.jpg"), range(1, 8));

        $this->actingAs($listing->owner)
            ->post(route('listings.images.store', $listing), ['images' => $images])
            ->assertSessionHasErrors(['images' => 'The Add new images field must not have more than 7 items.']);
    }

    public function test_setting_a_primary_moves_it_to_position_zero_and_keeps_positions_contiguous(): void
    {
        $listing = CarListing::factory()->withImages(3)->create();
        [$first, $second, $third] = $listing->images()->get()->all();

        $this->actingAs($listing->owner)
            ->patch(route('listings.images.primary', [$listing, $third]))
            ->assertRedirect(route('listings.images.edit', $listing))
            ->assertSessionHas('success', 'Primary image changed!');

        $this->assertSame([$third->id, $first->id, $second->id], $listing->images()->pluck('id')->all());
        $this->assertSame([0, 1, 2], $listing->images()->pluck('position')->all());
    }

    public function test_setting_the_current_primary_flashes_success_and_writes_nothing(): void
    {
        $listing = CarListing::factory()->approved()->withImages(2)->create(['updated_at' => now()->subDay()]);
        $before = $listing->fresh()->only(['status', 'submitted_at', 'approved_at', 'updated_at']);
        $primary = $listing->images()->where('position', 0)->sole();

        $this->actingAs($listing->owner)
            ->patch(route('listings.images.primary', [$listing, $primary]))
            ->assertRedirect(route('listings.images.edit', $listing))
            ->assertSessionHas('success', 'Primary image changed!');

        $this->assertEquals($before, $listing->fresh()->only(['status', 'submitted_at', 'approved_at', 'updated_at']));
        $this->assertSame(0, $primary->fresh()->position);
    }

    public function test_deleting_removes_the_row_and_its_three_files_and_closes_the_gap(): void
    {
        $listing = CarListing::factory()->withImages(3)->create();
        [$first, $second, $third] = $listing->images()->get()->all();
        $this->putFiles($second);
        $this->putFiles($third);

        $this->actingAs($listing->owner)
            ->delete(route('listings.images.destroy', [$listing, $second]))
            ->assertRedirect(route('listings.images.edit', $listing))
            ->assertSessionHas('success', 'Image deleted.');

        $this->assertModelMissing($second);
        Storage::disk('public')->assertMissing([$second->path, $second->card_path, $second->thumb_path]);
        Storage::disk('public')->assertExists([$third->path, $third->card_path, $third->thumb_path]);
        $this->assertSame([$first->id, $third->id], $listing->images()->pluck('id')->all());
        $this->assertSame([0, 1], $listing->images()->pluck('position')->all());
    }

    public function test_a_crafted_delete_of_the_primary_promotes_the_next_image(): void
    {
        $listing = CarListing::factory()->withImages(2)->create();
        [$first, $second] = $listing->images()->get()->all();

        $this->actingAs($listing->owner)->delete(route('listings.images.destroy', [$listing, $first]));

        $this->assertModelMissing($first);
        $this->assertSame(0, $second->fresh()->position);
    }

    public function test_deleting_the_last_image_is_refused_and_keeps_the_row_and_files(): void
    {
        $listing = CarListing::factory()->approved()->withImages(1)->create();
        $image = $listing->images()->sole();
        $this->putFiles($image);
        $before = $this->snapshot($listing);

        $this->actingAs($listing->owner)
            ->delete(route('listings.images.destroy', [$listing, $image]))
            ->assertRedirect(route('listings.images.edit', $listing))
            ->assertSessionHas('error', 'There needs to be at least one image!')
            ->assertSessionMissing('success');

        $this->assertModelExists($image);
        Storage::disk('public')->assertExists([$image->path, $image->card_path, $image->thumb_path]);
        $this->assertSame($before, $this->snapshot($listing));
    }

    #[DataProvider('writeRoutes')]
    public function test_every_write_ignores_a_foreign_referer(string $route): void
    {
        $listing = CarListing::factory()->withImages(2)->create();

        $this->actingAs($listing->owner);
        $this->write($route, $listing, ['Referer' => 'https://evil.example/phish'])
            ->assertRedirect(route('listings.images.edit', $listing));
    }

    /**
     * @return array<string, array{string, ListingStatus}>
     */
    public static function revertingWrites(): array
    {
        return [
            'add, approved' => ['listings.images.store', ListingStatus::Approved],
            'add, rejected' => ['listings.images.store', ListingStatus::Rejected],
            'delete, approved' => ['listings.images.destroy', ListingStatus::Approved],
            'primary, approved' => ['listings.images.primary', ListingStatus::Approved],
        ];
    }

    #[DataProvider('revertingWrites')]
    public function test_a_write_returns_a_moderated_listing_to_pending(string $route, ListingStatus $status): void
    {
        $factory = $status === ListingStatus::Approved ? CarListing::factory()->approved() : CarListing::factory()->rejected();
        $listing = $factory->withImages(2)->create(['submitted_at' => now()->subWeek()]);
        $moderator = $listing->moderated_by;
        $this->freezeSecond();

        $this->actingAs($listing->owner);
        $this->write($route, $listing)->assertSessionHas('success');

        $fresh = $listing->fresh();
        $this->assertSame(ListingStatus::Pending, $fresh->status);
        $this->assertEquals(now(), $fresh->submitted_at);
        $this->assertNull($fresh->approved_at);
        $this->assertNull($fresh->rejected_at);
        $this->assertNull($fresh->rejection_reason);
        $this->assertNotNull($moderator);
        $this->assertSame($moderator, $fresh->moderated_by);
    }

    #[DataProvider('imageRoutes')]
    public function test_an_admins_image_write_returns_an_approved_listing_to_pending(string $route): void
    {
        $listing = CarListing::factory()->approved()->withImages(2)->create();

        $this->actingAs(User::factory()->admin()->create());
        $this->write($route, $listing)->assertSessionHas('success');

        $this->assertSame(ListingStatus::Pending, $listing->fresh()->status);
    }

    public function test_an_admins_add_returns_an_approved_listing_to_pending(): void
    {
        $listing = CarListing::factory()->approved()->withImages(1)->create();

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('listings.images.store', $listing), ['images' => [$this->photo('a.jpg')]])
            ->assertSessionHas('success', 'Images gallery updated');

        $this->assertSame(ListingStatus::Pending, $listing->fresh()->status);
        $this->assertSame(2, $listing->images()->count());
    }

    public function test_a_suspended_owner_in_a_live_session_is_signed_out_and_writes_nothing(): void
    {
        $listing = CarListing::factory()->approved()->for(User::factory()->suspended(), 'owner')->withImages(2)->create();
        $image = $listing->images()->where('position', 1)->sole();

        $this->actingAs($listing->owner)
            ->patch(route('listings.images.primary', [$listing, $image]))
            ->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertSame(1, $image->fresh()->position);
        $this->assertSame(ListingStatus::Approved, $listing->fresh()->status);
    }

    #[DataProvider('writeRoutes')]
    public function test_a_pending_listing_stays_pending_without_a_new_submission_time(string $route): void
    {
        $submitted = now()->subWeek()->startOfSecond();
        $listing = CarListing::factory()->withImages(2)->create(['submitted_at' => $submitted]);

        $this->actingAs($listing->owner);
        $this->write($route, $listing)->assertSessionHas('success');

        $fresh = $listing->fresh();
        $this->assertSame(ListingStatus::Pending, $fresh->status);
        $this->assertEquals($submitted, $fresh->submitted_at);
    }

    /**
     * @param  array<string, string>  $headers
     */
    private function write(string $route, CarListing $listing, array $headers = []): TestResponse
    {
        $image = $listing->images()->where('position', 1)->first();
        $server = $this->transformHeadersToServerVars($headers);

        return match ($route) {
            'listings.images.store' => $this->call('POST', route($route, $listing), ['images' => [$this->photo('new.jpg')]], server: $server),
            'listings.images.primary' => $this->call('PATCH', route($route, [$listing, $image]), server: $server),
            'listings.images.destroy' => $this->call('DELETE', route($route, [$listing, $image]), server: $server),
        };
    }

    /**
     * @return array{positions: array<int, int>, listing: array<string, mixed>}
     */
    private function snapshot(CarListing $listing): array
    {
        return [
            'positions' => $listing->images()->pluck('position', 'id')->all(),
            'listing' => Arr::only($listing->fresh()->getAttributes(), ['status', 'submitted_at', 'approved_at', 'rejected_at', 'rejection_reason', 'updated_at']),
        ];
    }

    private function photo(string $name): UploadedFile
    {
        return UploadedFile::fake()->image($name, 40, 30);
    }

    private function putFiles(ListingImage $image): void
    {
        foreach ([$image->path, $image->card_path, $image->thumb_path] as $path) {
            Storage::disk('public')->put($path, 'x');
        }
    }
}
