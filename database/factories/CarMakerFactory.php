<?php

namespace Database\Factories;

use App\Models\CarMaker;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CarMaker>
 */
class CarMakerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Test Motors '.fake()->unique()->bothify('??####'),
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
