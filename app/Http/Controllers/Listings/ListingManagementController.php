<?php

namespace App\Http\Controllers\Listings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Listings\StoreListingRequest;
use App\Http\Requests\Listings\UpdateListingRequest;
use App\Models\CarListing;
use App\Services\ListingService;
use App\Support\BackUrl;
use App\Support\ListingFormat;
use App\Support\ListingFormValues;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ListingManagementController extends Controller
{
    public function create(ListingService $listings, BackUrl $backUrl): View
    {
        return view('pages.listings.create', [
            'options' => $listings->formOptions(),
            'backUrl' => $backUrl->resolve(route('profile')),
        ]);
    }

    public function store(StoreListingRequest $request, ListingService $listings): RedirectResponse
    {
        $listing = $listings->create($request->user(), $request->listingData(), $request->images());

        return redirect()->route('listings.show', $listing)
            ->with('success', __('app.listings.create.success'));
    }

    public function edit(CarListing $listing, ListingService $listings, BackUrl $backUrl): View
    {
        Gate::authorize('update', $listing);

        return view('pages.listings.edit', [
            'listing' => $listings->forEdit($listing),
            'options' => $listings->formOptions($listing),
            'values' => ListingFormValues::for($listing),
            'lastUpdated' => ListingFormat::date($listing->updated_at),
            'backUrl' => $backUrl->resolve(route('listings.show', $listing)),
        ]);
    }

    public function update(UpdateListingRequest $request, CarListing $listing, ListingService $listings): RedirectResponse
    {
        $listings->update($listing, $request->listingData());

        return redirect()->route('listings.show', $listing)
            ->with('success', __('app.listings.edit.updated'));
    }

    public function destroy(Request $request, CarListing $listing, ListingService $listings): RedirectResponse
    {
        Gate::authorize('delete', $listing);

        $listings->delete($listing);

        return redirect()->route($request->user()->isAdmin() ? 'admin.listings' : 'profile')
            ->with('success', __('app.listings.edit.deleted'));
    }
}
