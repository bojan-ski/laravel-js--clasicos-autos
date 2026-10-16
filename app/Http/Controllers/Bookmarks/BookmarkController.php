<?php

namespace App\Http\Controllers\Bookmarks;

use App\Http\Controllers\Controller;
use App\Models\CarListing;
use App\Services\BookmarkService;
use App\Support\BackUrl;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class BookmarkController extends Controller
{
    public function index(Request $request, BookmarkService $bookmarks): View
    {
        return view('pages.bookmarks.index', [
            'listings' => $bookmarks->paginate($request->user()),
        ]);
    }

    public function store(Request $request, CarListing $listing, BookmarkService $bookmarks, BackUrl $backUrl): JsonResponse|RedirectResponse
    {
        Gate::authorize('bookmark', $listing);

        $bookmarks->add($request->user(), $listing);

        return $request->expectsJson()
            ? response()->json(['bookmarked' => true, 'message' => __('app.bookmarks.flash.added')])
            : redirect($backUrl->resolve(route('listings.show', $listing)))->with('success', __('app.bookmarks.flash.added'));
    }

    public function destroy(Request $request, CarListing $listing, BookmarkService $bookmarks, BackUrl $backUrl): JsonResponse|RedirectResponse
    {
        Gate::authorize('bookmark', $listing);

        $bookmarks->remove($request->user(), $listing);

        return $request->expectsJson()
            ? response()->json(['bookmarked' => false, 'message' => __('app.bookmarks.flash.removed')])
            : redirect($backUrl->resolve(route('bookmarks.index')))->with('success', __('app.bookmarks.flash.removed'));
    }
}
