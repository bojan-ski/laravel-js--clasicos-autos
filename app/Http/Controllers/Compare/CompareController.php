<?php

namespace App\Http\Controllers\Compare;

use App\Http\Controllers\Controller;
use App\Models\CarListing;
use App\Services\CompareService;
use App\Support\BackUrl;
use App\Support\ComparisonRows;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

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

    public function add(Request $request, CarListing $listing, CompareService $compare, BackUrl $backUrl): JsonResponse|RedirectResponse
    {
        Gate::authorize('compare', $listing);

        if (! $compare->add($listing)) {
            return $request->expectsJson()
                ? response()->json(['message' => __('app.compare.flash.full')], Response::HTTP_CONFLICT)
                : redirect($backUrl->resolve(route('listings.index')))->with('error', __('app.compare.flash.full'));
        }

        return $request->expectsJson()
            ? $this->state($compare, true, __('app.compare.flash.added'))
            : redirect($backUrl->resolve(route('listings.index')))->with('success', __('app.compare.flash.added'));
    }

    public function remove(Request $request, CarListing $listing, CompareService $compare, BackUrl $backUrl): JsonResponse|RedirectResponse
    {
        $compare->remove($listing);

        return $request->expectsJson()
            ? $this->state($compare, false, __('app.compare.flash.removed'))
            : redirect($backUrl->resolve(route('compare.show')))->with('success', __('app.compare.flash.removed'));
    }

    public function clear(CompareService $compare, BackUrl $backUrl): RedirectResponse
    {
        $compare->clear();

        return redirect($backUrl->resolve(route('compare.show')))->with('success', __('app.compare.flash.cleared'));
    }

    private function state(CompareService $compare, bool $selected, string $message): JsonResponse
    {
        return response()->json([
            'selected' => $selected,
            'count' => count($compare->ids()),
            'message' => $message,
        ]);
    }
}
