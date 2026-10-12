<?php

namespace Tests\Feature\ReferenceData;

use App\Models\CarMaker;
use App\Models\CarModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ReferenceDataScopesTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_active_returns_only_active_makers(): void
    {
        $active = CarMaker::factory()->create();
        CarMaker::factory()->inactive()->create();

        $ids = CarMaker::query()->active()->pluck('id')->all();

        $this->assertSame([$active->id], $ids);
    }

    public function test_active_returns_only_active_models(): void
    {
        $active = CarModel::factory()->create();
        CarModel::factory()->inactive()->create();

        $ids = CarModel::query()->active()->pluck('id')->all();

        $this->assertSame([$active->id], $ids);
    }

    public function test_ordered_sorts_makers_by_name(): void
    {
        foreach (['Charlie Motors', 'Alpha Motors', 'Bravo Motors'] as $name) {
            CarMaker::factory()->create(['name' => $name]);
        }

        $names = CarMaker::query()->ordered()->pluck('name')->all();

        $this->assertSame(['Alpha Motors', 'Bravo Motors', 'Charlie Motors'], $names);
    }

    public function test_ordered_sorts_models_by_name(): void
    {
        foreach (['Charlie', 'Alpha', 'Bravo'] as $name) {
            CarModel::factory()->create(['name' => $name]);
        }

        $names = CarModel::query()->ordered()->pluck('name')->all();

        $this->assertSame(['Alpha', 'Bravo', 'Charlie'], $names);
    }

    public function test_active_models_returns_the_makers_active_models_by_name(): void
    {
        $maker = CarMaker::factory()->create();
        CarModel::factory()->for($maker, 'maker')->create(['name' => 'Charlie']);
        CarModel::factory()->for($maker, 'maker')->create(['name' => 'Alpha']);
        CarModel::factory()->for($maker, 'maker')->inactive()->create(['name' => 'Bravo']);
        CarModel::factory()->create(['name' => 'Able']);

        $names = $maker->activeModels()->pluck('name')->all();

        $this->assertSame(['Alpha', 'Charlie'], $names);
    }

    public function test_active_makers_load_their_active_models_in_two_queries(): void
    {
        $alpha = CarMaker::factory()->create(['name' => 'Alpha Motors']);
        $bravo = CarMaker::factory()->create(['name' => 'Bravo Motors']);
        CarMaker::factory()->inactive()->create(['name' => 'Charlie Motors']);
        CarModel::factory()->for($bravo, 'maker')->create(['name' => 'Zephyr']);
        CarModel::factory()->for($bravo, 'maker')->create(['name' => 'Coupe']);
        CarModel::factory()->for($alpha, 'maker')->inactive()->create(['name' => 'Sedan']);

        $this->expectsDatabaseQueryCount(2);

        $makers = CarMaker::query()->active()->ordered()->with('activeModels')->get();

        $this->assertSame(
            ['Alpha Motors' => [], 'Bravo Motors' => ['Coupe', 'Zephyr']],
            $makers->mapWithKeys(fn (CarMaker $maker): array => [
                $maker->name => $maker->activeModels->pluck('name')->all(),
            ])->all(),
        );
    }

    public function test_a_maker_without_models_is_deletable(): void
    {
        $maker = CarMaker::factory()->create();

        $this->assertTrue($maker->isDeletable());
    }

    public function test_a_maker_with_an_active_model_is_not_deletable(): void
    {
        $maker = CarModel::factory()->create()->maker()->sole();

        $this->assertFalse($maker->isDeletable());
    }

    public function test_a_maker_with_only_an_inactive_model_is_not_deletable(): void
    {
        $maker = CarModel::factory()->inactive()->create()->maker()->sole();

        $this->assertFalse($maker->isDeletable());
    }

    public function test_a_model_is_deletable(): void
    {
        $model = CarModel::factory()->create();

        $this->assertTrue($model->isDeletable());
    }
}
