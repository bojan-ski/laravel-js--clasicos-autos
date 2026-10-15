<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\InvalidListingStatusTransitionException;
use App\Exceptions\ListingNotPromotableException;
use App\Exceptions\StaleListingReviewException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ApproveListingRequest;
use App\Http\Requests\Admin\ModerateListingRequest;
use App\Http\Requests\Admin\PromoteListingModelRequest;
use App\Http\Requests\Admin\RejectListingRequest;
use App\Models\CarListing;
use App\Services\ListingModerationService;
use Closure;
use Illuminate\Http\RedirectResponse;

class ListingModerationController extends Controller
{
    public function approve(ApproveListingRequest $request, CarListing $listing, ListingModerationService $moderation): RedirectResponse
    {
        return $this->moderate($request, function () use ($moderation, $listing, $request): string {
            $moderation->approve($listing, $request->user(), $request->version());

            return __('app.listings.moderation.approved');
        });
    }

    public function reject(RejectListingRequest $request, CarListing $listing, ListingModerationService $moderation): RedirectResponse
    {
        return $this->moderate($request, function () use ($moderation, $listing, $request): string {
            $moderation->reject($listing, $request->user(), $request->reason(), $request->version());

            return __('app.listings.moderation.rejected');
        });
    }

    public function promote(PromoteListingModelRequest $request, CarListing $listing, ListingModerationService $moderation): RedirectResponse
    {
        return $this->moderate($request, function () use ($moderation, $listing, $request): string {
            $result = $moderation->promoteModel($listing, $request->name(), $request->yearFrom(), $request->yearTo(), $request->version());

            return __("app.admin.listings.promote.flash.{$result->value}", ['model' => $listing->model]);
        });
    }

    /**
     * @param  Closure(): string  $transition
     */
    private function moderate(ModerateListingRequest $request, Closure $transition): RedirectResponse
    {
        $redirect = redirect()->to($request->returnUrl());

        try {
            return $redirect->with('success', $transition());
        } catch (InvalidListingStatusTransitionException) {
            return $redirect->with('error', __('app.listings.moderation.not_pending'));
        } catch (ListingNotPromotableException) {
            return $redirect->with('error', __('app.admin.listings.promote.not_promotable'));
        } catch (StaleListingReviewException) {
            return $redirect->with('error', __('app.listings.moderation.changed'));
        }
    }
}
