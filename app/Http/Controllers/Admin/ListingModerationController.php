<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\InvalidListingStatusTransitionException;
use App\Exceptions\StaleListingReviewException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ApproveListingRequest;
use App\Http\Requests\Admin\RejectListingRequest;
use App\Models\CarListing;
use App\Services\ListingModerationService;
use Closure;
use Illuminate\Http\RedirectResponse;

class ListingModerationController extends Controller
{
    public function approve(ApproveListingRequest $request, CarListing $listing, ListingModerationService $moderation): RedirectResponse
    {
        return $this->moderate(
            $listing,
            fn () => $moderation->approve($listing, $request->user(), $request->version()),
            __('app.listings.moderation.approved'),
        );
    }

    public function reject(RejectListingRequest $request, CarListing $listing, ListingModerationService $moderation): RedirectResponse
    {
        return $this->moderate(
            $listing,
            fn () => $moderation->reject($listing, $request->user(), $request->reason(), $request->version()),
            __('app.listings.moderation.rejected'),
        );
    }

    private function moderate(CarListing $listing, Closure $transition, string $success): RedirectResponse
    {
        $redirect = redirect()->route('listings.show', $listing);

        try {
            $transition();
        } catch (InvalidListingStatusTransitionException) {
            return $redirect->with('error', __('app.listings.moderation.not_pending'));
        } catch (StaleListingReviewException) {
            return $redirect->with('error', __('app.listings.moderation.changed'));
        }

        return $redirect->with('success', $success);
    }
}
