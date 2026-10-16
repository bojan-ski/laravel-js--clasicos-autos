<?php

namespace App\View\Components\Listings;

use App\Models\CarMaker;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\View\Component;

class BrandChips extends Component
{
    /**
     * @param  Collection<int, CarMaker>  $makers
     * @param  array{search: string, makers: list<int>}  $filters
     */
    public function __construct(
        public Collection $makers,
        public array $filters,
    ) {}

    /**
     * @return list<array{label: string, url: string, active: bool}>
     */
    public function chips(): array
    {
        $selected = $this->filters['makers'];

        $chips = [[
            'label' => __('app.listings.index.all'),
            'url' => $this->url([]),
            'active' => $selected === [],
        ]];

        foreach ($this->makers as $maker) {
            $active = in_array($maker->id, $selected, true);

            $chips[] = [
                'label' => $maker->name,
                'url' => $this->url($active ? array_diff($selected, [$maker->id]) : [...$selected, $maker->id]),
                'active' => $active,
            ];
        }

        return $chips;
    }

    public function render(): View
    {
        return view('components.listings.brand-chips');
    }

    /**
     * @param  array<int, int>  $makerIds
     */
    private function url(array $makerIds): string
    {
        $makerIds = array_values($makerIds);
        sort($makerIds);

        return route('listings.index', array_filter([
            'search' => $this->filters['search'],
            'maker' => $makerIds,
        ], fn (string|array $value): bool => $value !== '' && $value !== []));
    }
}
