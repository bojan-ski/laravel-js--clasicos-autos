<?php

namespace App\Services;

use App\Exceptions\ListingImageRuleException;
use App\Models\CarListing;
use App\Models\ListingImage;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;
use InvalidArgumentException;
use LogicException;
use RuntimeException;
use Throwable;

class ListingImageService
{
    private ImageManager $manager;

    public function __construct(private ListingModerationService $moderation)
    {
        $this->manager = new ImageManager(new Driver);
    }

    /**
     * @param  list<UploadedFile>  $files
     * @return Collection<int, ListingImage>
     */
    public function storeForNewListing(CarListing $listing, array $files): Collection
    {
        $this->ensureNotEmpty($files);

        if (count($files) > config('listings.images.max')) {
            throw ListingImageRuleException::wouldExceedLimit();
        }

        if ($listing->images()->exists()) {
            throw new LogicException('The listing already has images.');
        }

        return $this->storeBatch($listing, $files, 0);
    }

    /**
     * @param  list<UploadedFile>  $files
     * @return Collection<int, ListingImage>
     */
    public function add(CarListing $listing, array $files): Collection
    {
        $this->ensureNotEmpty($files);

        return DB::transaction(function () use ($listing, $files): Collection {
            $locked = $this->lock($listing);
            $count = $locked->images()->lockForUpdate()->count();
            $max = config('listings.images.max');

            if ($count >= $max) {
                throw ListingImageRuleException::limitReached();
            }

            if ($count + count($files) > $max) {
                throw ListingImageRuleException::wouldExceedLimit();
            }

            $images = $this->storeBatch($locked, $files, $count);
            $this->moderation->returnToPending($locked);

            return $images;
        });
    }

    public function delete(CarListing $listing, ListingImage $image): void
    {
        DB::transaction(function () use ($listing, $image): void {
            $locked = $this->lock($listing);
            $image = $locked->images()->whereKey($image->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->images()->lockForUpdate()->count() === 1) {
                throw ListingImageRuleException::lastImageRequired();
            }

            $image->delete();
            $locked->images()->where('position', '>', $image->position)->decrement('position');
            $this->moderation->returnToPending($locked);

            DB::afterCommit(fn () => $this->deleteFiles([$image->path, $image->card_path, $image->thumb_path]));
        });
    }

    public function setPrimary(CarListing $listing, ListingImage $image): void
    {
        DB::transaction(function () use ($listing, $image): void {
            $locked = $this->lock($listing);
            $image = $locked->images()->whereKey($image->getKey())->lockForUpdate()->firstOrFail();

            if ($image->position === 0) {
                return;
            }

            $locked->images()->where('position', '<', $image->position)->increment('position');
            $image->update(['position' => 0]);
            $this->moderation->returnToPending($locked);
        });
    }

    /**
     * @param  list<int>  $listingIds
     */
    public function deleteListingDirectories(array $listingIds): void
    {
        foreach ($listingIds as $listingId) {
            Storage::disk('public')->deleteDirectory("listings/{$listingId}");
        }
    }

    /**
     * @param  list<UploadedFile>  $files
     */
    private function ensureNotEmpty(array $files): void
    {
        if ($files === []) {
            throw new InvalidArgumentException('At least one image is required.');
        }
    }

    private function lock(CarListing $listing): CarListing
    {
        return CarListing::query()->lockForUpdate()->findOrFail($listing->getKey());
    }

    /**
     * @param  list<UploadedFile>  $files
     * @return Collection<int, ListingImage>
     */
    private function storeBatch(CarListing $listing, array $files, int $firstPosition): Collection
    {
        $written = [];
        $rows = [];

        try {
            foreach ($files as $file) {
                $row = ['position' => $firstPosition + count($rows)];

                foreach ($this->encodeVariants($listing, $file) as $column => [$path, $contents]) {
                    $stored = Storage::disk('public')->put($path, $contents);
                    $written[] = $path;

                    if (! $stored) {
                        throw new RuntimeException("Unable to write {$path}.");
                    }

                    $row[$column] = $path;
                }

                $rows[] = $row;
            }
        } catch (Throwable $exception) {
            $this->deleteFiles($written);

            throw $exception;
        }

        DB::afterRollBack(fn () => $this->deleteFiles($written));

        return $listing->images()->createMany($rows);
    }

    /**
     * @return array<'path'|'card_path'|'thumb_path', array{string, string}>
     */
    private function encodeVariants(CarListing $listing, UploadedFile $file): array
    {
        $variants = config('listings.images.variants');
        $encoder = new WebpEncoder(quality: config('listings.images.webp_quality'));
        $directory = "listings/{$listing->getKey()}";
        $name = Str::uuid().'.webp';

        return [
            'path' => [
                "{$directory}/{$name}",
                (string) $this->manager->decodeSplFileInfo($file)->scaleDown($variants['full']['width'], $variants['full']['height'])->encode($encoder),
            ],
            'card_path' => [
                "{$directory}/card/{$name}",
                (string) $this->manager->decodeSplFileInfo($file)->cover($variants['card']['width'], $variants['card']['height'])->encode($encoder),
            ],
            'thumb_path' => [
                "{$directory}/thumb/{$name}",
                (string) $this->manager->decodeSplFileInfo($file)->cover($variants['thumb']['width'], $variants['thumb']['height'])->encode($encoder),
            ],
        ];
    }

    /**
     * @param  list<string>  $paths
     */
    private function deleteFiles(array $paths): void
    {
        if ($paths !== []) {
            Storage::disk('public')->delete($paths);
        }
    }
}
