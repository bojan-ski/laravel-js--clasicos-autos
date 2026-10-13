<?php

namespace App\Services;

use App\Models\CarListing;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class BookmarkService
{
    public function __construct(private Request $request) {}

    public function add(User $user, CarListing $listing): void
    {
        try {
            $user->bookmarks()->attach($listing->id);
        } catch (UniqueConstraintViolationException) {
        }

        $this->updateLoadedIds($user, fn (array $ids): array => in_array($listing->id, $ids, true) ? $ids : [...$ids, $listing->id]);
    }

    public function remove(User $user, CarListing $listing): void
    {
        $user->bookmarks()->detach($listing->id);

        $this->updateLoadedIds($user, fn (array $ids): array => array_values(array_filter($ids, fn (int $id): bool => $id !== $listing->id)));
    }

    /**
     * @return LengthAwarePaginator<int, CarListing>
     */
    public function paginate(User $user): LengthAwarePaginator
    {
        return $user->bookmarks()
            ->publiclyVisible()
            ->with(['maker', 'primaryImage', 'owner'])
            ->orderByPivotDesc('created_at')
            ->orderByDesc('car_listings.id')
            ->paginate(config('listings.per_page.bookmarks'));
    }

    public function isBookmarked(User $user, CarListing $listing): bool
    {
        return in_array($listing->id, $this->ids($user), true);
    }

    /**
     * @return list<int>
     */
    private function ids(User $user): array
    {
        $key = $this->key($user);

        if (! $this->request->attributes->has($key)) {
            $this->request->attributes->set($key, array_map(intval(...), $user->bookmarks()->allRelatedIds()->all()));
        }

        return $this->request->attributes->get($key);
    }

    /**
     * @param  callable(list<int>): list<int>  $change
     */
    private function updateLoadedIds(User $user, callable $change): void
    {
        $key = $this->key($user);

        if ($this->request->attributes->has($key)) {
            $this->request->attributes->set($key, $change($this->request->attributes->get($key)));
        }
    }

    private function key(User $user): string
    {
        return "bookmarks.ids.{$user->id}";
    }
}
