<?php

namespace App\Services;

use App\Enums\ListingStatus;
use App\Models\CarListing;
use App\Models\CarMaker;
use App\Models\CarModel;
use App\Models\User;
use App\Support\ListingFormOptions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class ListingService
{
    public function __construct(
        private ListingImageService $images,
        private ListingModerationService $moderation,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  list<UploadedFile>  $images
     */
    public function create(User $owner, array $data, array $images): CarListing
    {
        if ($data['car_model_id'] !== null) {
            $data['model'] = CarModel::query()->whereKey($data['car_model_id'])->value('name');
        }

        return DB::transaction(function () use ($owner, $data, $images): CarListing {
            $listing = $owner->listings()->make($data);
            $listing->status = ListingStatus::Pending;
            $listing->submitted_at = now();
            $listing->save();

            $this->images->storeForNewListing($listing, $images);

            return $listing;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(CarListing $listing, array $data): CarListing
    {
        return DB::transaction(function () use ($listing, $data): CarListing {
            $locked = CarListing::query()->lockForUpdate()->findOrFail($listing->getKey());

            if ($data['car_model_id'] !== null) {
                $data['model'] = CarModel::query()->whereKey($data['car_model_id'])->value('name');
            }

            $locked->fill($data);
            $locked->save();

            $this->moderation->returnToPending($locked);

            return $locked;
        });
    }

    public function delete(CarListing $listing): void
    {
        DB::transaction(function () use ($listing): void {
            $listing->delete();

            DB::afterCommit(fn () => $this->images->deleteListingDirectories([$listing->id]));
        });
    }

    public function forEdit(CarListing $listing): CarListing
    {
        return $listing->load('primaryImage');
    }

    public function formOptions(?CarListing $current = null): ListingFormOptions
    {
        $makers = CarMaker::query()
            ->where(fn (Builder $query) => $query->active()->when(
                $current !== null,
                fn (Builder $query) => $query->orWhereKey($current->car_maker_id),
            ))
            ->ordered()
            ->with('activeModels')
            ->get(['id', 'name']);

        return ListingFormOptions::fromMakers($makers, $current?->loadMissing('carModel')->carModel);
    }
}
