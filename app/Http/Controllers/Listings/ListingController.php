<?php

namespace App\Http\Controllers\Listings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Listings\ListingIndexRequest;
use App\Services\ListingBrowseService;
use Illuminate\Contracts\View\View;

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
}
