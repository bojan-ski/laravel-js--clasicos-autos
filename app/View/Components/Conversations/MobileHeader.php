<?php

namespace App\View\Components\Conversations;

use App\Models\CarListing;
use App\Support\ListingFormat;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class MobileHeader extends Component
{
    public function __construct(public CarListing $listing, public string $name, public string $backUrl) {}

    public function price(): string
    {
        return ListingFormat::price($this->listing->price);
    }

    public function imageUrl(): ?string
    {
        return $this->listing->primaryImage?->thumbUrl();
    }

    public function render(): View
    {
        return view('components.conversations.mobile-header');
    }
}
