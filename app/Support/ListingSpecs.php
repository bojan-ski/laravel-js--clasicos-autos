<?php

namespace App\Support;

use App\Models\CarListing;
use Illuminate\Support\Str;

final readonly class ListingSpecs
{
    public function __construct(public CarListing $listing) {}

    public function kicker(): string
    {
        return __('app.listings.show.kicker', [
            'maker' => $this->listing->maker->name,
            'model' => $this->listing->model,
            'year' => $this->listing->year,
        ]);
    }

    public function price(): string
    {
        return ListingFormat::price($this->listing->price);
    }

    /**
     * @return list<array{label: string, value: string}>
     */
    public function details(): array
    {
        $listing = $this->listing;
        $hasEngine = $listing->engine_size !== null || $listing->engine_type !== null;

        return $this->rows([
            __('app.listings.show.specs.make') => $listing->maker->name,
            __('app.listings.show.specs.model') => $listing->model,
            __('app.listings.show.specs.year') => (string) $listing->year,
            __('app.listings.show.specs.mileage') => ListingFormat::mileage($listing->mileage),
            __('app.listings.show.specs.transmission') => $listing->transmission->label(),
            __('app.listings.show.specs.fuel_type') => $listing->fuel_type->label(),
            __('app.listings.show.specs.engine') => $hasEngine ? trim($listing->engine_size.' '.$listing->engine_type) : null,
            __('app.listings.show.specs.location') => __('app.listings.show.specs.location_value', [
                'city' => $listing->location_city,
                'state' => $listing->location_state,
                'zipcode' => $listing->location_zipcode,
            ]),
            __('app.listings.show.specs.posted') => ListingFormat::date($listing->created_at),
        ]);
    }

    /**
     * @return list<array{label: string, value: string}>
     */
    public function exteriorInterior(): array
    {
        $listing = $this->listing;

        return $this->rows([
            __('app.listings.show.specs.body_type') => $listing->body_type?->label(),
            __('app.listings.show.specs.exterior_color') => $listing->exterior_color !== null ? Str::ucfirst($listing->exterior_color) : null,
            __('app.listings.show.specs.interior_color') => $listing->interior_color !== null ? Str::ucfirst($listing->interior_color) : null,
            __('app.listings.show.specs.exterior_condition') => $listing->exterior_condition?->label(),
            __('app.listings.show.specs.interior_condition') => $listing->interior_condition?->label(),
            __('app.listings.show.specs.seat_material') => $listing->seat_material?->label(),
        ]);
    }

    /**
     * @return list<array{label: string, value: string}>
     */
    public function engineHistory(): array
    {
        $listing = $this->listing;

        return $this->rows([
            __('app.listings.show.specs.engine_history') => $listing->engine_history->label(),
            __('app.listings.show.specs.engine_condition') => $listing->engine_condition->label(),
            __('app.listings.show.specs.odometer') => $listing->odometer->label(),
            __('app.listings.show.specs.restoration') => $listing->restoration_history->label(),
            __('app.listings.show.specs.original_parts') => $listing->original_parts_percentage !== null
                ? __('app.listings.show.specs.original_parts_value', ['percentage' => $listing->original_parts_percentage])
                : null,
            __('app.listings.show.specs.license_plate_type') => $listing->license_plate_type?->label(),
            __('app.listings.show.specs.documentation') => $listing->documentation_status->label(),
        ]);
    }

    /**
     * @return list<array{label: string, value: string}>
     */
    public function owner(): array
    {
        $owner = $this->listing->owner;

        return $this->rows([
            __('app.listings.show.owner.email') => $owner->email,
            __('app.listings.show.owner.phone') => $owner->phone_number,
            __('app.listings.show.owner.joined') => ListingFormat::date($owner->created_at),
            __('app.listings.show.owner.total_listings') => (string) $owner->approved_listings_count,
        ]);
    }

    /**
     * @param  array<string, string|null>  $values
     * @return list<array{label: string, value: string}>
     */
    private function rows(array $values): array
    {
        $rows = [];

        foreach ($values as $label => $value) {
            if ($value !== null) {
                $rows[] = ['label' => $label, 'value' => $value];
            }
        }

        return $rows;
    }
}
