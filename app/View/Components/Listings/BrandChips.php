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
     * @param  array{search: string, maker: int|null}  $filters
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
        $chips = [[
            'label' => __('app.listings.index.all'),
            'url' => $this->url(null),
            'active' => $this->filters['maker'] === null,
        ]];

        foreach ($this->makers as $maker) {
            $chips[] = [
                'label' => $maker->name,
                'url' => $this->url($maker->id),
                'active' => $this->filters['maker'] === $maker->id,
            ];
        }

        return $chips;
    }

    public function render(): View
    {
        return view('components.listings.brand-chips');
    }

    private function url(?int $makerId): string
    {
        return route('listings.index', array_filter([
            'search' => $this->filters['search'],
            'maker' => $makerId,
        ], fn (string|int|null $value): bool => $value !== null && $value !== ''));
    }
}
