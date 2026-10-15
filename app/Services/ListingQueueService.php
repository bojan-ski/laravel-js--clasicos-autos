<?php

namespace App\Services;

use App\Enums\ListingQueueFilter;
use App\Enums\ListingStatus;
use App\Models\CarListing;
use App\Support\LikePattern;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class ListingQueueService
{
    /**
     * @return LengthAwarePaginator<int, CarListing>
     */
    public function paginate(ListingQueueFilter $filter, string $search): LengthAwarePaginator
    {
        $direction = $filter === ListingQueueFilter::Pending ? 'asc' : 'desc';

        return CarListing::query()
            ->when($filter->status(), fn (Builder $query, ListingStatus $status) => $query->where('status', $status))
            ->when($search !== '', function (Builder $query) use ($search): void {
                $pattern = LikePattern::contains($search);

                $query->where(fn (Builder $match) => $match
                    ->where('name', 'like', $pattern)
                    ->orWhereHas('owner', fn (Builder $owner) => $owner
                        ->where('username', 'like', $pattern)
                        ->orWhere('email', 'like', $pattern)));
            })
            ->with(['owner', 'maker', 'primaryImage'])
            ->orderBy('submitted_at', $direction)
            ->orderBy('id', $direction)
            ->paginate(config('listings.per_page.admin'))
            ->withQueryString();
    }

    public function pendingCount(): int
    {
        return CarListing::query()->where('status', ListingStatus::Pending)->count();
    }
}
