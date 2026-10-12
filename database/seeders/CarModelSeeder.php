<?php

namespace Database\Seeders;

use App\Models\CarMaker;
use Illuminate\Database\Seeder;
use RuntimeException;

class CarModelSeeder extends Seeder
{
    public function run(): void
    {
        /** @var array<string, list<array{name: string, year_from: int, year_to: int}>> $modelsByMaker */
        $modelsByMaker = require database_path('seeders/data/car_models.php');

        $makers = [];
        $missing = [];

        foreach (array_keys($modelsByMaker) as $makerName) {
            $maker = CarMaker::query()->firstWhere('name', $makerName);

            if ($maker === null) {
                $missing[] = $makerName;
            } else {
                $makers[$makerName] = $maker;
            }
        }

        if ($missing !== []) {
            throw new RuntimeException('Car makers missing; seed them first: '.implode(', ', $missing).'.');
        }

        foreach ($modelsByMaker as $makerName => $models) {
            foreach ($models as $model) {
                $makers[$makerName]->models()->firstOrCreate(
                    ['name' => $model['name']],
                    ['year_from' => $model['year_from'], 'year_to' => $model['year_to']],
                );
            }
        }
    }
}
