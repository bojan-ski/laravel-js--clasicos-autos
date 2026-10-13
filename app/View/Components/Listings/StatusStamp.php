<?php

namespace App\View\Components\Listings;

use App\Enums\ListingStatus;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class StatusStamp extends Component
{
    public function __construct(public ListingStatus $status) {}

    public function tone(): string
    {
        return match ($this->status) {
            ListingStatus::Pending => 'status-pending',
            ListingStatus::Approved => 'status-approved',
            ListingStatus::Rejected => 'status-rejected',
        };
    }

    public function render(): View
    {
        return view('components.listings.status-stamp');
    }
}
