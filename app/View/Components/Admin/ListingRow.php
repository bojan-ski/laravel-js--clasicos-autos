<?php

namespace App\View\Components\Admin;

use App\Models\CarListing;
use App\Support\SuspensionText;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class ListingRow extends Component
{
    /**
     * @param  array{status?: string, search?: string, page?: int}  $returnQuery
     */
    public function __construct(public CarListing $listing, public array $returnQuery) {}

    public function thumbUrl(): ?string
    {
        return $this->listing->primaryImage?->thumbUrl();
    }

    public function makerAndModel(): string
    {
        return __('app.admin.listings.maker_model', ['maker' => $this->listing->maker->name, 'model' => $this->listing->model]);
    }

    public function submitted(): string
    {
        return $this->listing->submitted_at->format('d.m.Y');
    }

    public function ownerStatus(): ?string
    {
        return $this->listing->owner->isSuspended() ? SuspensionText::status($this->listing->owner) : null;
    }

    public function showsActions(): bool
    {
        return $this->listing->isPending();
    }

    public function showsPromote(): bool
    {
        return $this->listing->canPromoteModel();
    }

    public function approveUrl(): string
    {
        return $this->actionUrl('admin.listings.approve');
    }

    public function rejectUrl(): string
    {
        return $this->actionUrl('admin.listings.reject');
    }

    public function promoteUrl(): string
    {
        return $this->actionUrl('admin.listings.promote');
    }

    public function rejectDialogId(): string
    {
        return "reject-listing-{$this->listing->id}";
    }

    public function promoteDialogId(): string
    {
        return "promote-model-{$this->listing->id}";
    }

    public function render(): View
    {
        return view('components.admin.listing-row');
    }

    private function actionUrl(string $route): string
    {
        return route($route, [$this->listing, 'return' => 'queue', ...$this->returnQuery]);
    }
}
