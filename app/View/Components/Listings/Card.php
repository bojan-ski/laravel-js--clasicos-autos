<?php

namespace App\View\Components\Listings;

use App\Models\CarListing;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class Card extends Component
{
    public function __construct(
        public CarListing $listing,
        public bool $showStatus = false,
    ) {}

    public function price(): string
    {
        return '$'.number_format($this->listing->price);
    }

    public function posted(): string
    {
        return __('app.listings.card.posted', ['diff' => $this->listing->created_at->diffForHumans()]);
    }

    public function imageUrl(): ?string
    {
        return $this->listing->primaryImage?->cardUrl();
    }

    public function showsStatus(): bool
    {
        return $this->showStatus && ! $this->listing->isApproved();
    }

    public function render(): View
    {
        return view('components.listings.card');
    }
}
