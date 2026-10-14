<?php

namespace App\Http\Controllers\Listings;

use App\Exceptions\ListingImageRuleException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Listings\StoreListingImagesRequest;
use App\Models\CarListing;
use App\Models\ListingImage;
use App\Services\ListingImageService;
use App\Services\ListingService;
use App\Support\BackUrl;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class ListingImageController extends Controller
{
    public function edit(CarListing $listing, ListingService $listings, BackUrl $backUrl): View
    {
        Gate::authorize('update', $listing);

        $listing = $listings->forImages($listing);
        $maxImages = config('listings.images.max');

        return view('pages.listings.images', [
            'listing' => $listing,
            'primaryImage' => $listing->images->first(),
            'imageCount' => $listing->images->count(),
            'maxImages' => $maxImages,
            'canAddImages' => $listing->images->count() < $maxImages,
            'backUrl' => $backUrl->resolve(route('listings.show', $listing)),
        ]);
    }

    public function store(StoreListingImagesRequest $request, CarListing $listing, ListingImageService $images): RedirectResponse
    {
        try {
            $images->add($listing, $request->images());
        } catch (ListingImageRuleException $exception) {
            return $this->backToImages($listing)->with('error', $exception->getMessage());
        }

        return $this->backToImages($listing)->with('success', __('app.listings.images.gallery_updated'));
    }

    public function primary(CarListing $listing, ListingImage $image, ListingImageService $images): RedirectResponse
    {
        Gate::authorize('update', $listing);

        $images->setPrimary($listing, $image);

        return $this->backToImages($listing)->with('success', __('app.listings.images.primary_changed'));
    }

    public function destroy(CarListing $listing, ListingImage $image, ListingImageService $images): RedirectResponse
    {
        Gate::authorize('update', $listing);

        try {
            $images->delete($listing, $image);
        } catch (ListingImageRuleException $exception) {
            return $this->backToImages($listing)->with('error', $exception->getMessage());
        }

        return $this->backToImages($listing)->with('success', __('app.listings.images.deleted'));
    }

    private function backToImages(CarListing $listing): RedirectResponse
    {
        return redirect()->route('listings.images.edit', $listing);
    }
}
