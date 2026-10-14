<?php

namespace App\Services;

use App\Enums\ListingStatus;
use App\Models\CarListing;

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
}
