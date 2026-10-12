<?php

namespace Tests\Feature\ReferenceData;

use App\Models\CarMaker;
use Database\Seeders\CarMakerSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CarMakerSeederTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const MAKERS = [
        'Alfa Romeo', 'Aston Martin', 'Austin-Healey', 'Bentley', 'Buick', 'Cadillac', 'Chevrolet', 'Chrysler',
        'Citroën', 'Datsun', 'DeSoto', 'Dodge', 'Ferrari', 'Fiat', 'Ford', 'Hudson', 'Jaguar', 'Lamborghini',
        'Lancia', 'Lincoln', 'Maserati', 'Mercedes-Benz', 'Mercury', 'MG', 'Nash', 'Oldsmobile', 'Packard',
        'Peugeot', 'Plymouth', 'Pontiac', 'Porsche', 'Renault', 'Rolls-Royce', 'Studebaker', 'Triumph',
        'Volkswagen', 'Volvo',
    ];

    public function test_it_seeds_exactly_the_37_makers_all_active(): void
    {
        $this->seed(CarMakerSeeder::class);

        $this->assertSame(self::MAKERS, CarMaker::query()->orderBy('id')->pluck('name')->all());
        $this->assertDatabaseMissing('car_makers', ['is_active' => false]);
    }

    public function test_a_second_run_creates_nothing_and_keeps_a_deactivated_maker_inactive(): void
    {
        $this->seed(CarMakerSeeder::class);
        $ford = CarMaker::query()->where('name', 'Ford')->sole();
        $ford->is_active = false;
        $ford->save();
        $before = CarMaker::query()->orderBy('id')->get()->toArray();
        $this->travel(1)->hours();

        $this->seed(CarMakerSeeder::class);

        $this->assertSame($before, CarMaker::query()->orderBy('id')->get()->toArray());
        $this->assertFalse($ford->fresh()->is_active);
    }
}
