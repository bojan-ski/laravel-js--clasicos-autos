<?php

namespace App\Http\Controllers\Listings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Listings\AdvancedSearchRequest;
use App\Http\Requests\Listings\ListingIndexRequest;
use App\Models\CarListing;
use App\Services\ListingBrowseService;
use App\Support\BackUrl;
use App\Support\ListingSpecs;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ListingController extends Controller
{
    public function index(ListingIndexRequest $request, ListingBrowseService $listings): View
    {
        $filters = $request->filters();

        return view('pages.listings.index', [
            'listings' => $listings->paginate($filters),
            'filters' => $filters,
            'filtered' => $request->hasFilters(),
            'makers' => $listings->brandMakers(),
        ]);
    }

    public function advanced(AdvancedSearchRequest $request, ListingBrowseService $listings): View
    {
        $filters = $request->filters();

        return view('pages.listings.advanced', [
            'listings' => $listings->advanced($filters),
            'filters' => $filters,
            'filtered' => $request->hasFilters(),
            'makers' => $listings->brandMakers(),
        ]);
    }

    public function show(Request $request, CarListing $listing, ListingBrowseService $listings, BackUrl $backUrl): View
    {
        Gate::authorize('view', $listing);

        $listing = $listings->detail($listing);

        return view('pages.listings.show', [
            'listing' => $listing,
            'specs' => new ListingSpecs($listing),
            'viewerIsOwner' => $listing->isOwnedBy($request->user()),
            'backUrl' => $backUrl->resolve(route('listings.index')),
        ]);
    }
}
