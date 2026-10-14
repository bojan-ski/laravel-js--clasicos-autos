<?php

namespace App\Services;

use App\Enums\ListingStatus;
use App\Models\CarListing;
use App\Models\CarMaker;
use App\Models\CarModel;
use App\Models\User;
use App\Support\ListingFormOptions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class ListingService
{
    public function __construct(private ListingImageService $images) {}

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

    public function formOptions(): ListingFormOptions
    {
        return ListingFormOptions::fromMakers(
            CarMaker::query()->active()->ordered()->with('activeModels')->get(['id', 'name']),
        );
    }
}
