<?php

namespace App\View\Components\Admin;

use App\Enums\ListingQueueFilter;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class ListingStatusChips extends Component
{
    public function __construct(public ListingQueueFilter $filter, public string $search) {}

    /**
     * @return list<array{label: string, url: string, active: bool}>
     */
    public function chips(): array
    {
        $chips = [];

        foreach (ListingQueueFilter::cases() as $filter) {
            $chips[] = [
                'label' => $filter->label(),
                'url' => route('admin.listings', array_filter([
                    'status' => $filter->queryValue(),
                    'search' => $this->search === '' ? null : $this->search,
                ])),
                'active' => $filter === $this->filter,
            ];
        }

        return $chips;
    }

    public function render(): View
    {
        return view('components.admin.listing-status-chips');
    }
}
