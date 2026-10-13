<?php

namespace App\View\Components\Listings;

use App\Models\CarListing;
use App\Services\CompareService;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class CompareButton extends Component
{
    public function __construct(
        private CompareService $compare,
        public CarListing $listing,
    ) {}

    public function selected(): bool
    {
        return $this->compare->contains($this->listing);
    }

    public function actionUrl(): string
    {
        return route($this->selected() ? 'compare.remove' : 'compare.add', $this->listing);
    }

    public function stateClasses(): string
    {
        return $this->selected()
            ? 'border-red bg-red/10 text-red'
            : 'border-rule text-ink-2 hover:bg-ink/7';
    }

    public function render(): View
    {
        return view('components.listings.compare-button');
    }
}
