<?php

namespace App\Http\Controllers\Compare;

use App\Http\Controllers\Controller;
use App\Models\CarListing;
use App\Services\CompareService;
use App\Support\BackUrl;
use App\Support\ComparisonRows;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class CompareController extends Controller
{
    public function show(CompareService $compare, BackUrl $backUrl): View
    {
        $listings = $compare->listings();

        return view('pages.compare.show', [
            'listings' => $listings,
            'rows' => $listings->isEmpty() ? [] : (new ComparisonRows($listings[0], $listings[1] ?? null))->rows(),
            'backUrl' => $backUrl->resolve(route('listings.index')),
        ]);
    }

    public function add(CarListing $listing, CompareService $compare, BackUrl $backUrl): RedirectResponse
    {
        Gate::authorize('compare', $listing);

        $redirect = redirect($backUrl->resolve(route('listings.index')));

        return $compare->add($listing)
            ? $redirect->with('success', __('app.compare.flash.added'))
            : $redirect->with('error', __('app.compare.flash.full'));
    }

    public function remove(CarListing $listing, CompareService $compare, BackUrl $backUrl): RedirectResponse
    {
        $compare->remove($listing);

        return redirect($backUrl->resolve(route('compare.show')))->with('success', __('app.compare.flash.removed'));
    }

    public function clear(CompareService $compare, BackUrl $backUrl): RedirectResponse
    {
        $compare->clear();

        return redirect($backUrl->resolve(route('compare.show')))->with('success', __('app.compare.flash.cleared'));
    }
}
