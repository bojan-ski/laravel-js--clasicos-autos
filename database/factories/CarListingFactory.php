<?php

namespace Database\Factories;

use App\Enums\DocumentationStatus;
use App\Enums\EngineCondition;
use App\Enums\EngineHistory;
use App\Enums\FuelType;
use App\Enums\ListingStatus;
use App\Enums\Odometer;
use App\Enums\RestorationHistory;
use App\Enums\Transmission;
use App\Models\CarListing;
use App\Models\CarMaker;
use App\Models\CarModel;
use App\Models\ListingImage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\Sequence;

/**
 * @extends Factory<CarListing>
 */
class CarListingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'car_model_id' => CarModel::factory(),
            'car_maker_id' => fn (array $attributes) => $attributes['car_model_id'] === null
                ? CarMaker::factory()
                : $this->carModelOf($attributes)->car_maker_id,
            'name' => ucfirst(fake()->words(3, true)),
            'model' => fn (array $attributes) => $this->carModelOf($attributes)->name,
            'description' => fake()->paragraph(),
            'year' => function (array $attributes) {
                $carModel = $this->carModelOf($attributes);

                return fake()->numberBetween($carModel->year_from, $carModel->year_to);
            },
            'mileage' => fake()->numberBetween(0, 300000),
            'price' => fake()->numberBetween(1000, 250000),
            'transmission' => fake()->randomElement(Transmission::cases()),
            'fuel_type' => fake()->randomElement(FuelType::cases()),
            'engine_history' => fake()->randomElement(EngineHistory::cases()),
            'engine_condition' => fake()->randomElement(EngineCondition::cases()),
            'restoration_history' => fake()->randomElement(RestorationHistory::cases()),
            'odometer' => fake()->randomElement(Odometer::cases()),
            'documentation_status' => fake()->randomElement(DocumentationStatus::cases()),
            'location_city' => fake()->city(),
            'location_state' => fake()->state(),
            'location_zipcode' => fake()->postcode(),
            'status' => ListingStatus::Pending,
            'submitted_at' => now(),
            'approved_at' => null,
            'rejected_at' => null,
            'rejection_reason' => null,
            'moderated_by' => null,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ListingStatus::Approved,
            'approved_at' => now(),
            'rejected_at' => null,
            'rejection_reason' => null,
            'moderated_by' => User::factory()->admin(),
        ]);
    }

    public function rejected(?string $reason = null): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ListingStatus::Rejected,
            'approved_at' => null,
            'rejected_at' => now(),
            'rejection_reason' => $reason ?? 'The photos do not show the car clearly.',
            'moderated_by' => User::factory()->admin(),
        ]);
    }

    public function customModel(): static
    {
        return $this->state(fn (array $attributes) => [
            'car_model_id' => null,
            'model' => 'Custom '.ucfirst(fake()->word()),
            'year' => fake()->numberBetween(config('listings.min_year'), config('listings.max_year')),
        ]);
    }

    public function forCarModel(CarModel $carModel): static
    {
        return $this->state(fn (array $attributes) => [
            'car_model_id' => $carModel->id,
            'car_maker_id' => $carModel->car_maker_id,
            'model' => $carModel->name,
            'year' => fake()->numberBetween($carModel->year_from, $carModel->year_to),
        ]);
    }

    public function withImages(int $count = 1): static
    {
        return $this->has(
            ListingImage::factory()
                ->count($count)
                ->sequence(fn (Sequence $sequence) => ['position' => $sequence->index]),
            'images',
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function carModelOf(array $attributes): CarModel
    {
        return CarModel::query()->findOrFail($attributes['car_model_id']);
    }
}
