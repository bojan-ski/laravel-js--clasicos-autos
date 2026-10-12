<?php

namespace Database\Seeders;

use App\Models\CarMaker;
use Illuminate\Database\Seeder;

class CarMakerSeeder extends Seeder
{
    public function run(): void
    {
        /** @var list<string> $names */
        $names = require database_path('seeders/data/car_makers.php');

        foreach ($names as $name) {
            CarMaker::query()->firstOrCreate(['name' => $name]);
        }
    }
}
