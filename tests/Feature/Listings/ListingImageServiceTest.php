<?php

namespace Tests\Feature\Listings;

use App\Enums\ListingStatus;
use App\Exceptions\ListingImageRuleException;
use App\Models\CarListing;
use App\Models\ListingImage;
use App\Services\ListingImageService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Exceptions\DecoderException;
use InvalidArgumentException;
use LogicException;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;
use Throwable;

class ListingImageServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    private ListingImageService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->service = app(ListingImageService::class);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function inputFormats(): array
    {
        return [
            'jpeg' => ['jpg'],
            'png' => ['png'],
        ];
    }

    #[DataProvider('inputFormats')]
    public function test_an_upload_becomes_three_webp_variants_sharing_one_uuid(string $extension): void
    {
        $listing = CarListing::factory()->create();

        $image = $this->service->storeForNewListing($listing, [UploadedFile::fake()->image("car.{$extension}", 120, 90)])->sole();

        $uuid = basename($image->path, '.webp');
        $this->assertTrue(Str::isUuid($uuid));
        $this->assertSame("listings/{$listing->id}/{$uuid}.webp", $image->path);
        $this->assertSame("listings/{$listing->id}/card/{$uuid}.webp", $image->card_path);
        $this->assertSame("listings/{$listing->id}/thumb/{$uuid}.webp", $image->thumb_path);
        $this->assertEqualsCanonicalizing(
            [$image->path, $image->card_path, $image->thumb_path],
            Storage::disk('public')->allFiles("listings/{$listing->id}"),
        );

        foreach ([$image->path, $image->card_path, $image->thumb_path] as $path) {
            $this->assertSame('image/webp', Storage::disk('public')->mimeType($path));
        }
    }

    /**
     * @return array<string, array{int, int, array{int, int}}>
     */
    public static function inputSizes(): array
    {
        return [
            'wide landscape' => [3000, 1500, [2000, 1000]],
            'tall portrait' => [1500, 3000, [1000, 2000]],
            'smaller than the bound' => [1200, 900, [1200, 900]],
        ];
    }

    /**
     * @param  array{int, int}  $expectedFull
     */
    #[DataProvider('inputSizes')]
    public function test_variants_have_the_pinned_sizes(int $width, int $height, array $expectedFull): void
    {
        $listing = CarListing::factory()->create();

        $image = $this->service->storeForNewListing($listing, [UploadedFile::fake()->image('car.jpg', $width, $height)])->sole();

        $this->assertSame($expectedFull, $this->dimensions($image->path));
        $this->assertSame([800, 600], $this->dimensions($image->card_path));
        $this->assertSame([320, 240], $this->dimensions($image->thumb_path));
    }

    public function test_a_photo_with_exif_orientation_comes_out_upright(): void
    {
        $listing = CarListing::factory()->create();
        $landscape = UploadedFile::fake()->image('source.jpg', 60, 40);
        $jpeg = file_get_contents($landscape->getRealPath());
        $rotatedClockwise = "\xFF\xE1\x00\x22Exif\x00\x00MM\x00\x2A\x00\x00\x00\x08\x00\x01\x01\x12\x00\x03\x00\x00\x00\x01\x00\x06\x00\x00\x00\x00\x00\x00";
        $portrait = UploadedFile::fake()->createWithContent('portrait.jpg', substr($jpeg, 0, 2).$rotatedClockwise.substr($jpeg, 2));

        $image = $this->service->storeForNewListing($listing, [$portrait])->sole();

        $this->assertSame([40, 60], $this->dimensions($image->path));
    }

    public function test_store_for_a_new_listing_keeps_upload_order_and_leaves_the_status_alone(): void
    {
        $this->freezeSecond();
        $listing = CarListing::factory()->create();
        $submittedAt = $listing->submitted_at;
        $this->travel(1)->hours();

        $images = $this->service->storeForNewListing($listing, [
            UploadedFile::fake()->image('a.jpg', 100, 50),
            UploadedFile::fake()->image('b.jpg', 60, 120),
            UploadedFile::fake()->image('c.jpg', 90, 90),
        ]);

        $this->assertSame([0, 1, 2], $listing->images()->pluck('position')->all());
        $this->assertSame([[100, 50], [60, 120], [90, 90]], $images->map(fn (ListingImage $image) => $this->dimensions($image->path))->all());
        $listing->refresh();
        $this->assertSame(ListingStatus::Pending, $listing->status);
        $this->assertEquals($submittedAt, $listing->submitted_at);
    }

    public function test_store_for_a_new_listing_refuses_an_empty_batch(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service->storeForNewListing(CarListing::factory()->create(), []);
    }

    public function test_store_for_a_new_listing_refuses_more_than_the_limit(): void
    {
        $listing = CarListing::factory()->create();

        $exception = $this->thrown(fn () => $this->service->storeForNewListing($listing, $this->uploads(9)));

        $this->assertInstanceOf(ListingImageRuleException::class, $exception);
        $this->assertSame(__('app.listings.images.would_exceed_limit'), $exception->getMessage());
        $this->assertSame(0, $listing->images()->count());
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_store_for_a_new_listing_refuses_a_listing_that_already_has_images(): void
    {
        $listing = CarListing::factory()->withImages(1)->create();

        $exception = $this->thrown(fn () => $this->service->storeForNewListing($listing, $this->uploads(1)));

        $this->assertInstanceOf(LogicException::class, $exception);
        $this->assertSame(1, $listing->images()->count());
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_add_appends_after_the_last_position(): void
    {
        $listing = CarListing::factory()->withImages(2)->create();

        $added = $this->service->add($listing, $this->uploads(2));

        $this->assertSame([2, 3], $added->pluck('position')->all());
        $this->assertSame([0, 1, 2, 3], $listing->images()->pluck('position')->all());
        $this->assertCount(6, Storage::disk('public')->allFiles("listings/{$listing->id}"));
    }

    public function test_add_refuses_a_listing_at_the_limit(): void
    {
        $listing = CarListing::factory()->approved()->withImages(8)->create();

        $exception = $this->thrown(fn () => $this->service->add($listing, $this->uploads(1)));

        $this->assertInstanceOf(ListingImageRuleException::class, $exception);
        $this->assertSame('The limit is 8 images!', $exception->getMessage());
        $this->assertNothingAdded($listing, 8);
    }

    public function test_add_refuses_a_batch_that_would_exceed_the_limit(): void
    {
        $listing = CarListing::factory()->approved()->withImages(7)->create();

        $exception = $this->thrown(fn () => $this->service->add($listing, $this->uploads(2)));

        $this->assertInstanceOf(ListingImageRuleException::class, $exception);
        $this->assertSame('Adding new images will exceed the limit of 8 images!', $exception->getMessage());
        $this->assertNothingAdded($listing, 7);
    }

    public function test_add_refuses_an_empty_batch(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service->add(CarListing::factory()->withImages(1)->create(), []);
    }

    public function test_a_batch_with_an_undecodable_file_leaves_no_file_and_no_row(): void
    {
        $listing = CarListing::factory()->approved()->withImages(1)->create();

        $exception = $this->thrown(fn () => $this->service->add($listing, [
            UploadedFile::fake()->image('good.jpg', 120, 90),
            UploadedFile::fake()->create('broken.jpg', 10, 'image/jpeg'),
        ]));

        $this->assertInstanceOf(DecoderException::class, $exception);
        $this->assertNothingAdded($listing, 1);
    }

    public function test_a_failed_file_write_leaves_no_file_and_no_row(): void
    {
        $listing = CarListing::factory()->approved()->withImages(1)->create();
        $realDisk = Storage::disk('public');
        $failingDisk = Mockery::mock($realDisk);
        $failingDisk->shouldReceive('put')->andReturnUsing(
            fn (string $path, string $contents) => ! str_contains($path, '/card/') && $realDisk->put($path, $contents),
        );
        Storage::set('public', $failingDisk);

        $exception = $this->thrown(fn () => $this->service->add($listing, $this->uploads(1)));

        Storage::set('public', $realDisk);
        $this->assertInstanceOf(RuntimeException::class, $exception);
        $this->assertNothingAdded($listing, 1);
    }

    public function test_a_rolled_back_caller_transaction_leaves_no_file_of_the_batch(): void
    {
        $listing = CarListing::factory()->create();

        $exception = $this->thrown(fn () => DB::transaction(function () use ($listing): void {
            $this->service->storeForNewListing($listing, $this->uploads(2));

            throw new RuntimeException('The create transaction failed.');
        }));

        $this->assertInstanceOf(RuntimeException::class, $exception);
        $this->assertSame(0, $listing->images()->count());
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_a_committed_caller_transaction_keeps_the_batch(): void
    {
        $listing = CarListing::factory()->create();

        $images = DB::transaction(fn () => $this->service->storeForNewListing($listing, $this->uploads(2)));

        $this->assertSame(2, $listing->images()->count());
        $this->assertCount(6, Storage::disk('public')->allFiles("listings/{$listing->id}"));
        Storage::disk('public')->assertExists($images->pluck('path')->all());
    }

    public function test_delete_removes_the_row_and_its_files_and_closes_the_gap(): void
    {
        $listing = CarListing::factory()->create();
        [$first, $second, $third] = $this->service->storeForNewListing($listing, $this->uploads(3))->all();

        $this->service->delete($listing, $second);

        $this->assertModelMissing($second);
        $this->assertSame([$first->id, $third->id], $listing->images()->pluck('id')->all());
        $this->assertSame([0, 1], $listing->images()->pluck('position')->all());
        Storage::disk('public')->assertMissing([$second->path, $second->card_path, $second->thumb_path]);
        Storage::disk('public')->assertExists([$first->path, $third->path]);
    }

    public function test_deleting_the_primary_promotes_the_next_image(): void
    {
        $listing = CarListing::factory()->withImages(3)->create();
        [$first, $second] = $listing->images()->get()->all();

        $this->service->delete($listing, $first);

        $this->assertTrue($listing->primaryImage()->sole()->is($second));
        $this->assertSame([0, 1], $listing->images()->pluck('position')->all());
    }

    public function test_delete_refuses_the_only_image(): void
    {
        $listing = CarListing::factory()->approved()->create();
        $image = $this->service->storeForNewListing($listing, $this->uploads(1))->sole();

        $exception = $this->thrown(fn () => $this->service->delete($listing, $image));

        $this->assertInstanceOf(ListingImageRuleException::class, $exception);
        $this->assertSame('There needs to be at least one image!', $exception->getMessage());
        $this->assertModelExists($image);
        Storage::disk('public')->assertExists([$image->path, $image->card_path, $image->thumb_path]);
        $this->assertSame(ListingStatus::Approved, $listing->fresh()->status);
    }

    public function test_set_primary_moves_the_image_to_the_front_and_shifts_the_earlier_ones(): void
    {
        $listing = CarListing::factory()->withImages(4)->create();
        [$a, $b, $c, $d] = $listing->images()->get()->all();

        $this->service->setPrimary($listing, $c);

        $this->assertSame([$c->id, $a->id, $b->id, $d->id], $listing->images()->pluck('id')->all());
        $this->assertSame([0, 1, 2, 3], $listing->images()->pluck('position')->all());
    }

    public function test_set_primary_on_the_primary_writes_nothing(): void
    {
        $listing = CarListing::factory()->approved()->withImages(2)->create();
        $this->travel(1)->hours();
        $before = $listing->images()->get(['id', 'position', 'updated_at'])->toArray();

        $this->service->setPrimary($listing, $listing->primaryImage()->sole());

        $this->assertSame($before, $listing->images()->get(['id', 'position', 'updated_at'])->toArray());
        $this->assertSame(ListingStatus::Approved, $listing->fresh()->status);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function imageOperations(): array
    {
        return [
            'delete' => ['delete'],
            'set primary' => ['setPrimary'],
        ];
    }

    #[DataProvider('imageOperations')]
    public function test_an_image_of_another_listing_is_not_found(string $operation): void
    {
        $listing = CarListing::factory()->approved()->withImages(2)->create();
        $foreign = CarListing::factory()->withImages(2)->create()->images()->get()->last();

        $exception = $this->thrown(fn () => $this->service->{$operation}($listing, $foreign));

        $this->assertInstanceOf(ModelNotFoundException::class, $exception);
        $this->assertModelExists($foreign);
        $this->assertSame(1, $foreign->fresh()->position);
        $this->assertSame([0, 1], $listing->images()->pluck('position')->all());
        $this->assertSame(ListingStatus::Approved, $listing->fresh()->status);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function imageChangesOnModeratedListings(): array
    {
        $cases = [];

        foreach (['approved', 'rejected'] as $state) {
            foreach (['add', 'delete', 'setPrimary'] as $operation) {
                $cases["{$operation} on {$state}"] = [$state, $operation];
            }
        }

        return $cases;
    }

    #[DataProvider('imageChangesOnModeratedListings')]
    public function test_an_image_change_returns_a_moderated_listing_to_pending(string $state, string $operation): void
    {
        $this->freezeSecond();
        $listing = CarListing::factory()->{$state}()->withImages(2)->create();
        $moderatorId = $listing->moderated_by;
        $this->travel(1)->hours();

        $this->performImageChange($operation, $listing);

        $listing->refresh();
        $this->assertSame(ListingStatus::Pending, $listing->status);
        $this->assertEquals(now(), $listing->submitted_at);
        $this->assertNull($listing->approved_at);
        $this->assertNull($listing->rejected_at);
        $this->assertNull($listing->rejection_reason);
        $this->assertSame($moderatorId, $listing->moderated_by);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function imageChanges(): array
    {
        return [
            'add' => ['add'],
            'delete' => ['delete'],
            'set primary' => ['setPrimary'],
        ];
    }

    #[DataProvider('imageChanges')]
    public function test_an_image_change_leaves_a_pending_listing_untouched(string $operation): void
    {
        $this->freezeSecond();
        $listing = CarListing::factory()->withImages(2)->create();
        $submittedAt = $listing->submitted_at;
        $this->travel(1)->hours();

        $this->performImageChange($operation, $listing);

        $listing->refresh();
        $this->assertSame(ListingStatus::Pending, $listing->status);
        $this->assertEquals($submittedAt, $listing->submitted_at);
    }

    public function test_delete_listing_directories_removes_only_the_given_listings(): void
    {
        $disk = Storage::disk('public');
        $disk->put('listings/1/a.webp', 'x');
        $disk->put('listings/1/card/a.webp', 'x');
        $disk->put('listings/1/thumb/a.webp', 'x');
        $disk->put('listings/2/b.webp', 'x');

        $this->service->deleteListingDirectories([1, 999]);

        $this->assertFalse($disk->directoryExists('listings/1'));
        $disk->assertExists('listings/2/b.webp');
    }

    private function performImageChange(string $operation, CarListing $listing): void
    {
        $second = $listing->images()->where('position', 1)->first();

        match ($operation) {
            'add' => $this->service->add($listing, $this->uploads(1)),
            'delete' => $this->service->delete($listing, $second),
            'setPrimary' => $this->service->setPrimary($listing, $second),
        };
    }

    private function assertNothingAdded(CarListing $listing, int $expectedCount): void
    {
        $this->assertSame($expectedCount, $listing->images()->count());
        $this->assertSame([], Storage::disk('public')->allFiles());
        $this->assertSame(ListingStatus::Approved, $listing->fresh()->status);
    }

    /**
     * @return list<UploadedFile>
     */
    private function uploads(int $count): array
    {
        return array_map(fn (int $number) => UploadedFile::fake()->image("car-{$number}.jpg", 40, 30), range(1, $count));
    }

    /**
     * @return array{int, int}
     */
    private function dimensions(string $path): array
    {
        [$width, $height] = getimagesizefromstring(Storage::disk('public')->get($path));

        return [$width, $height];
    }

    private function thrown(callable $action): Throwable
    {
        try {
            $action();
        } catch (Throwable $exception) {
            return $exception;
        }

        $this->fail('No exception was thrown.');
    }
}
