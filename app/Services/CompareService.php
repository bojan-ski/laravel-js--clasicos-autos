<?php

namespace App\Services;

use App\Models\CarListing;
use Illuminate\Contracts\Session\Session;
use Illuminate\Database\Eloquent\Collection;

class CompareService
{
    public const string SESSION_KEY = 'compare_listings';

    public const int MAX = 2;

    public function __construct(private Session $session) {}

    /**
     * @return list<int>
     */
    public function ids(): array
    {
        $held = $this->held();

        if ($held === []) {
            return [];
        }

        $public = CarListing::query()->publiclyVisible()->whereKey($held)->pluck('id')->map(fn (int|string $id): int => (int) $id)->all();

        return $this->prune($held, $public);
    }

    /**
     * @return Collection<int, CarListing>
     */
    public function listings(): Collection
    {
        $held = $this->held();

        if ($held === []) {
            return new Collection;
        }

        $listings = CarListing::query()->publiclyVisible()->whereKey($held)->with(['maker', 'primaryImage'])->get()->keyBy('id');
        $ids = $this->prune($held, $listings->keys()->map(fn (int|string $id): int => (int) $id)->all());

        return new Collection(array_map(fn (int $id): CarListing => $listings[$id], $ids));
    }

    public function contains(CarListing $listing): bool
    {
        return in_array($listing->id, $this->held(), true);
    }

    public function add(CarListing $listing): bool
    {
        $ids = $this->ids();

        if (in_array($listing->id, $ids, true) || count($ids) >= self::MAX) {
            return false;
        }

        $this->session->put(self::SESSION_KEY, [...$ids, $listing->id]);

        return true;
    }

    public function remove(CarListing $listing): void
    {
        $held = $this->held();
        $kept = array_values(array_filter($held, fn (int $id): bool => $id !== $listing->id));

        if ($kept !== $held) {
            $this->session->put(self::SESSION_KEY, $kept);
        }
    }

    public function clear(): void
    {
        $this->session->forget(self::SESSION_KEY);
    }

    /**
     * @return list<int>
     */
    private function held(): array
    {
        return array_values(array_map(intval(...), (array) $this->session->get(self::SESSION_KEY, [])));
    }

    /**
     * @param  list<int>  $held
     * @param  list<int>  $public
     * @return list<int>
     */
    private function prune(array $held, array $public): array
    {
        $ids = array_values(array_filter($held, fn (int $id): bool => in_array($id, $public, true)));

        if ($ids !== $held) {
            $this->session->put(self::SESSION_KEY, $ids);
        }

        return $ids;
    }
}
