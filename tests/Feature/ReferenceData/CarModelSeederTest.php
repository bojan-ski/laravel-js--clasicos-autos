<?php

namespace Tests\Feature\ReferenceData;

use App\Models\CarMaker;
use App\Models\CarModel;
use Database\Seeders\CarMakerSeeder;
use Database\Seeders\CarModelSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class CarModelSeederTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_every_maker_gets_five_to_fifteen_active_models(): void
    {
        $this->seed([CarMakerSeeder::class, CarModelSeeder::class]);

        $counts = CarMaker::query()->withCount('models')->pluck('models_count', 'name');

        $this->assertCount(37, $counts);
        foreach ($counts as $maker => $count) {
            $this->assertGreaterThanOrEqual(5, $count, $maker);
            $this->assertLessThanOrEqual(15, $count, $maker);
        }
        $this->assertDatabaseMissing('car_models', ['is_active' => false]);
    }

    public function test_seeded_models_lie_within_the_classic_year_bounds(): void
    {
        $this->seed([CarMakerSeeder::class, CarModelSeeder::class]);

        foreach (CarModel::query()->get() as $model) {
            $this->assertGreaterThanOrEqual(config('listings.min_year'), $model->year_from, $model->name);
            $this->assertGreaterThanOrEqual($model->year_from, $model->year_to, $model->name);
            $this->assertLessThanOrEqual(config('listings.max_year'), $model->year_to, $model->name);
        }
    }

    public function test_seeded_model_names_are_short_unique_per_maker_and_free_of_the_maker_name(): void
    {
        $this->seed([CarMakerSeeder::class, CarModelSeeder::class]);

        foreach (CarMaker::query()->with('models')->get() as $maker) {
            $names = $maker->models->pluck('name');
            $makerPattern = '/\b'.preg_quote($maker->name, '/').'\b/iu';

            foreach ($names as $name) {
                $this->assertLessThanOrEqual(64, mb_strlen($name), $name);
                $this->assertDoesNotMatchRegularExpression($makerPattern, $name);
            }
            $folded = $names->map(fn (string $name): string => Str::lower(Str::ascii($name)));
            $this->assertSame($folded->count(), $folded->unique()->count(), $maker->name);
        }
    }

    public function test_a_second_run_creates_nothing_and_keeps_edited_models(): void
    {
        $this->seed([CarMakerSeeder::class, CarModelSeeder::class]);
        $mustang = CarModel::query()->where('name', 'Mustang')->sole();
        $mustang->year_to = 1973;
        $mustang->save();
        $beetle = CarModel::query()->where('name', 'Beetle')->sole();
        $beetle->is_active = false;
        $beetle->save();
        $before = CarModel::query()->orderBy('id')->get()->toArray();
        $this->travel(1)->hours();

        $this->seed(CarModelSeeder::class);

        $this->assertSame($before, CarModel::query()->orderBy('id')->get()->toArray());
        $this->assertSame(1973, $mustang->fresh()->year_to);
        $this->assertFalse($beetle->fresh()->is_active);
    }

    public function test_a_missing_maker_is_named_and_no_model_is_created(): void
    {
        $this->seed(CarMakerSeeder::class);
        CarMaker::query()->where('name', 'Volvo')->delete();

        try {
            $this->seed(CarModelSeeder::class);
            $this->fail('The seeder ran with a missing maker.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('Volvo', $exception->getMessage());
            $this->assertStringNotContainsString('Ford', $exception->getMessage());
        }

        $this->assertDatabaseCount('car_models', 0);
    }

    public function test_without_makers_every_maker_is_named(): void
    {
        try {
            $this->seed(CarModelSeeder::class);
            $this->fail('The seeder ran without makers.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('Alfa Romeo', $exception->getMessage());
            $this->assertStringContainsString('Volvo', $exception->getMessage());
        }

        $this->assertDatabaseCount('car_models', 0);
    }
}
