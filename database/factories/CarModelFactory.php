<?php

namespace Database\Factories;

use App\Models\CarMaker;
use App\Models\CarModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CarModel>
 */
class CarModelFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $maxYear = config('listings.max_year');
        $yearFrom = fake()->numberBetween(config('listings.min_year'), $maxYear);

        return [
            'car_maker_id' => CarMaker::factory(),
            'name' => 'Model '.fake()->unique()->bothify('??####'),
            'year_from' => $yearFrom,
            'year_to' => fake()->numberBetween($yearFrom, $maxYear),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
