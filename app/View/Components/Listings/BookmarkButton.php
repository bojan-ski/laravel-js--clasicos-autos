<?php

namespace App\View\Components\Listings;

use App\Models\CarListing;
use App\Services\BookmarkService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\Component;

class BookmarkButton extends Component
{
    public function __construct(
        private BookmarkService $bookmarks,
        public CarListing $listing,
    ) {}

    public function bookmarked(): bool
    {
        return $this->bookmarks->isBookmarked(Auth::user(), $this->listing);
    }

    public function actionUrl(): string
    {
        return route($this->bookmarked() ? 'bookmarks.destroy' : 'bookmarks.store', $this->listing);
    }

    public function stateClasses(): string
    {
        return $this->bookmarked()
            ? 'border-red bg-red/10'
            : 'border-rule hover:bg-ink/7';
    }

    public function render(): View
    {
        return view('components.listings.bookmark-button');
    }
}
