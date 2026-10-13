<?php

namespace App\Services;

use App\Models\CarListing;
use App\Models\CarMaker;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class ListingBrowseService
{
    /**
     * @param  array{search: string, maker: int|null}  $filters
     * @return LengthAwarePaginator<int, CarListing>
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        return $this->publicListings()
            ->when($filters['search'] !== '', function (Builder $query) use ($filters): void {
                $pattern = '%'.$this->escapeLike($filters['search']).'%';

                $query->where(fn (Builder $search) => $search
                    ->where('name', 'like', $pattern)
                    ->orWhere('model', 'like', $pattern)
                    ->orWhereHas('maker', fn (Builder $maker) => $maker->where('name', 'like', $pattern)));
            })
            ->when($filters['maker'] !== null, fn (Builder $query) => $query->where('car_maker_id', $filters['maker']))
            ->paginate(config('listings.per_page.listings'))
            ->withQueryString();
    }

    /**
     * @return Collection<int, CarListing>
     */
    public function latest(int $limit): Collection
    {
        return $this->publicListings()->limit($limit)->get();
    }

    /**
     * @return Collection<int, CarMaker>
     */
    public function brandMakers(): Collection
    {
        return CarMaker::query()->active()->ordered()->get(['id', 'name']);
    }

    /**
     * @return Builder<CarListing>
     */
    private function publicListings(): Builder
    {
        return CarListing::query()
            ->publiclyVisible()
            ->with(['maker', 'primaryImage'])
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }

    private function escapeLike(string $term): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term);
    }
}
