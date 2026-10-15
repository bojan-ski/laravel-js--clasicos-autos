<?php

namespace App\Http\Controllers\Users;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ListingBrowseService;
use App\Support\BackUrl;
use App\Support\ListingFormat;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SellerListingController extends Controller
{
    public function index(Request $request, User $user, ListingBrowseService $listings, BackUrl $backUrl): View
    {
        Gate::authorize('viewListings', $user);

        $viewer = $request->user();
        $everyStatus = $viewer->seesEveryListingOf($user);

        return view('pages.users.listings', [
            'seller' => $user,
            'listings' => $listings->sellerListings($user, $everyStatus),
            'showsStatus' => $everyStatus,
            'joined' => ListingFormat::date($user->created_at),
            'backUrl' => $backUrl->resolve(route('listings.index')),
        ]);
    }
}
