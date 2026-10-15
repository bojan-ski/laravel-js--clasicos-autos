<?php

namespace App\Services;

use App\Enums\ListingStatus;
use App\Models\CarListing;
use App\Models\CarMaker;
use App\Models\User;
use App\Support\LikePattern;
use BackedEnum;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ListingBrowseService
{
    private const array ADVANCED_EQUALITY = [
        'car_maker' => 'car_maker_id',
        'transmission' => 'transmission',
        'fuel_type' => 'fuel_type',
        'body_type' => 'body_type',
        'engine_size' => 'engine_size',
        'engine_type' => 'engine_type',
        'engine_history' => 'engine_history',
        'engine_condition' => 'engine_condition',
        'restoration_history' => 'restoration_history',
        'odometer' => 'odometer',
        'license_plate_type' => 'license_plate_type',
        'documentation_status' => 'documentation_status',
        'exterior_condition' => 'exterior_condition',
        'interior_condition' => 'interior_condition',
        'seat_material' => 'seat_material',
    ];

    private const array ADVANCED_SUBSTRING = [
        'model' => 'model',
        'exterior_color' => 'exterior_color',
        'interior_color' => 'interior_color',
        'city' => 'location_city',
        'state' => 'location_state',
        'zipcode' => 'location_zipcode',
    ];

    /**
     * @var array<string, array{string, string}>
     */
    private const array ADVANCED_BOUNDS = [
        'year_from' => ['year', '>='],
        'year_to' => ['year', '<='],
        'price_from' => ['price', '>='],
        'price_to' => ['price', '<='],
        'mileage' => ['mileage', '<='],
    ];

    /**
     * @param  array{search: string, maker: int|null}  $filters
     * @return LengthAwarePaginator<int, CarListing>
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        return $this->publicListings()
            ->when($filters['search'] !== '', function (Builder $query) use ($filters): void {
                $pattern = LikePattern::contains($filters['search']);

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
     * @param  array<string, string|int|BackedEnum|null>  $filters
     * @return LengthAwarePaginator<int, CarListing>
     */
    public function advanced(array $filters): LengthAwarePaginator
    {
        $query = $this->publicListings();

        foreach (self::ADVANCED_EQUALITY as $key => $column) {
            $query->when($this->isFilled($filters[$key]), fn (Builder $listings) => $listings->where($column, $filters[$key]));
        }

        foreach (self::ADVANCED_SUBSTRING as $key => $column) {
            $query->when($this->isFilled($filters[$key]), fn (Builder $listings) => $listings->where($column, 'like', LikePattern::contains($filters[$key])));
        }

        foreach (self::ADVANCED_BOUNDS as $key => [$column, $operator]) {
            $query->when($this->isFilled($filters[$key]), fn (Builder $listings) => $listings->where($column, $operator, $filters[$key]));
        }

        return $query
            ->paginate(config('listings.per_page.advanced'))
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
     * @return LengthAwarePaginator<int, CarListing>
     */
    public function sellerListings(User $seller, bool $everyStatus): LengthAwarePaginator
    {
        return $this->ownedBy($seller)
            ->when(! $everyStatus, fn (Builder $listings) => $listings->publiclyVisible())
            ->paginate(config('listings.per_page.seller'));
    }

    /**
     * @return LengthAwarePaginator<int, CarListing>
     */
    public function ownListings(User $owner): LengthAwarePaginator
    {
        return $this->ownedBy($owner)->paginate(config('listings.per_page.profile'));
    }

    public function detail(CarListing $listing): CarListing
    {
        return $listing->load([
            'owner' => fn (BelongsTo $owner) => $owner->withCount([
                'listings as approved_listings_count' => fn (Builder $listings) => $listings->where($listings->qualifyColumn('status'), ListingStatus::Approved),
            ]),
            'maker',
            'images',
        ]);
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
            ->with(['owner', 'maker', 'primaryImage'])
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }

    /**
     * @return Builder<CarListing>
     */
    private function ownedBy(User $owner): Builder
    {
        $query = CarListing::query()->whereBelongsTo($owner, 'owner');

        return $query
            ->with(['owner', 'maker', 'primaryImage'])
            ->orderByDesc($query->qualifyColumn('created_at'))
            ->orderByDesc($query->qualifyColumn('id'));
    }

    private function isFilled(string|int|BackedEnum|null $value): bool
    {
        return $value !== null && $value !== '';
    }
}
