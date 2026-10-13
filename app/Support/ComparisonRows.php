<?php

namespace App\Support;

use App\Models\CarListing;
use Illuminate\Support\Str;

final readonly class ComparisonRows
{
    private const string EMPTY = '-';

    public function __construct(
        public CarListing $first,
        public ?CarListing $second = null,
    ) {}

    /**
     * @return list<array{label: string, first: string, second: string, differs: bool}>
     */
    public function rows(): array
    {
        $first = $this->values($this->first);
        $second = $this->second !== null ? $this->values($this->second) : null;
        $rows = [];

        foreach ($first as $key => $value) {
            $other = $second[$key] ?? self::EMPTY;

            $rows[] = [
                'label' => __("app.compare.rows.{$key}"),
                'first' => $value,
                'second' => $other,
                'differs' => $second !== null && $value !== $other,
            ];
        }

        return $rows;
    }

    /**
     * @return array<string, string>
     */
    private function values(CarListing $listing): array
    {
        $values = [
            'model' => $listing->model,
            'car_maker' => $listing->maker->name,
            'year' => (string) $listing->year,
            'mileage' => ListingFormat::mileage($listing->mileage),
            'exterior_color' => $listing->exterior_color !== null ? Str::ucfirst($listing->exterior_color) : null,
            'interior_color' => $listing->interior_color !== null ? Str::ucfirst($listing->interior_color) : null,
            'transmission' => $listing->transmission->label(),
            'fuel_type' => $listing->fuel_type->label(),
            'engine_size' => $listing->engine_size,
            'engine_type' => $listing->engine_type,
            'odometer' => $listing->odometer->label(),
            'exterior_condition' => $listing->exterior_condition?->label(),
            'interior_condition' => $listing->interior_condition?->label(),
            'seat_material' => $listing->seat_material?->label(),
            'engine_history' => $listing->engine_history->label(),
            'engine_condition' => $listing->engine_condition->label(),
            'price' => ListingFormat::price($listing->price),
            'location_city' => $listing->location_city,
            'location_state' => $listing->location_state,
            'body_type' => $listing->body_type?->label(),
            'restoration_history' => $listing->restoration_history->label(),
            'original_parts_percentage' => $listing->original_parts_percentage !== null ? (string) $listing->original_parts_percentage : null,
            'license_plate_type' => $listing->license_plate_type?->label(),
            'documentation_status' => $listing->documentation_status->label(),
        ];

        return array_map(fn (?string $value): string => $value ?? self::EMPTY, $values);
    }
}
