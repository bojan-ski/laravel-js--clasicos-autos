<?php

namespace Database\Factories;

use App\Models\CarListing;
use App\Models\ListingImage;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ListingImage>
 */
class ListingImageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $uuid = (string) Str::uuid();

        return [
            'car_listing_id' => CarListing::factory(),
            'position' => 0,
            'path' => fn (array $attributes) => "listings/{$attributes['car_listing_id']}/{$uuid}.webp",
            'card_path' => fn (array $attributes) => "listings/{$attributes['car_listing_id']}/card/{$uuid}.webp",
            'thumb_path' => fn (array $attributes) => "listings/{$attributes['car_listing_id']}/thumb/{$uuid}.webp",
        ];
    }
}
