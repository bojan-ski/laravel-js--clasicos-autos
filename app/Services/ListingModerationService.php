<?php

namespace App\Services;

use App\Enums\ListingStatus;
use App\Exceptions\InvalidListingStatusTransitionException;
use App\Exceptions\StaleListingReviewException;
use App\Models\CarListing;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ListingModerationService
{
    public function returnToPending(CarListing $listing): void
    {
        if (! $listing->shouldReturnToPendingOnEdit()) {
            return;
        }

        $listing->status = ListingStatus::Pending;
        $listing->submitted_at = now();
        $listing->approved_at = null;
        $listing->rejected_at = null;
        $listing->rejection_reason = null;
        $listing->save();
    }

    public function approve(CarListing $listing, User $admin, int $version): void
    {
        DB::transaction(function () use ($listing, $admin, $version): void {
            $locked = $this->lock($listing);

            if (! $locked->canBeApproved()) {
                throw $this->notPending($locked, 'approved');
            }

            $this->ensureUnchanged($locked, $version);

            $locked->status = ListingStatus::Approved;
            $locked->approved_at = now();
            $locked->rejected_at = null;
            $locked->rejection_reason = null;
            $locked->moderated_by = $admin->getKey();
            $locked->save();
        });
    }

    public function reject(CarListing $listing, User $admin, string $reason, int $version): void
    {
        DB::transaction(function () use ($listing, $admin, $reason, $version): void {
            $locked = $this->lock($listing);

            if (! $locked->canBeRejected()) {
                throw $this->notPending($locked, 'rejected');
            }

            $this->ensureUnchanged($locked, $version);

            $locked->status = ListingStatus::Rejected;
            $locked->rejected_at = now();
            $locked->rejection_reason = $reason;
            $locked->approved_at = null;
            $locked->moderated_by = $admin->getKey();
            $locked->save();
        });
    }

    private function lock(CarListing $listing): CarListing
    {
        return CarListing::query()->lockForUpdate()->findOrFail($listing->getKey());
    }

    private function notPending(CarListing $listing, string $transition): InvalidListingStatusTransitionException
    {
        return new InvalidListingStatusTransitionException(
            "Listing {$listing->getKey()} cannot be {$transition} from status {$listing->status->value}."
        );
    }

    private function ensureUnchanged(CarListing $listing, int $version): void
    {
        if ($listing->moderationVersion() !== $version) {
            throw new StaleListingReviewException(
                "Listing {$listing->getKey()} changed since review (version {$version}, now {$listing->moderationVersion()})."
            );
        }
    }
}
