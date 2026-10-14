<?php

namespace App\Http\Controllers\Listings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Listings\StoreListingRequest;
use App\Services\ListingService;
use App\Support\BackUrl;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

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
}
