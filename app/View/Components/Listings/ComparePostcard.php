<?php

namespace App\View\Components\Listings;

use App\Models\CarListing;
use App\Support\ListingFormat;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class ComparePostcard extends Component
{
    public function __construct(
        public ?CarListing $listing = null,
        public string $tilt = 'left',
    ) {}

    public function tiltClass(): string
    {
        return $this->tilt === 'right' ? 'rotate-1' : '-rotate-1';
    }

    public function imageUrl(): ?string
    {
        return $this->listing?->primaryImage?->cardUrl();
    }

    public function price(): string
    {
        return $this->listing !== null ? ListingFormat::price($this->listing->price) : '';
    }

    public function removeUrl(): string
    {
        return $this->listing !== null ? route('compare.remove', $this->listing) : '';
    }

    public function render(): View
    {
        return view('components.listings.compare-postcard');
    }
}
