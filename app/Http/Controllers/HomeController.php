<?php

namespace App\Http\Controllers;

use App\Services\ListingBrowseService;
use Illuminate\Contracts\View\View;

class HomeController extends Controller
{
    public function __invoke(ListingBrowseService $listings): View
    {
        return view('pages.home', [
            'latestListings' => $listings->latest(3),
        ]);
    }
}
