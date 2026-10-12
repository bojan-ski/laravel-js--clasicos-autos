<?php

namespace Tests\Feature\ReferenceData;

use App\Models\CarMaker;
use App\Models\CarModel;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ReferenceDataSchemaTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_car_makers_has_exactly_the_contract_columns(): void
    {
        $columns = collect(Schema::getColumns('car_makers'))->pluck('type', 'name')->all();

        $this->assertSame([
            'id' => 'bigint unsigned',
            'name' => 'varchar(64)',
            'is_active' => 'tinyint(1)',
            'created_at' => 'timestamp',
            'updated_at' => 'timestamp',
        ], $columns);
    }

    public function test_car_models_has_exactly_the_contract_columns(): void
    {
        $columns = collect(Schema::getColumns('car_models'))->pluck('type', 'name')->all();

        $this->assertSame([
            'id' => 'bigint unsigned',
            'car_maker_id' => 'bigint unsigned',
            'name' => 'varchar(64)',
            'year_from' => 'smallint unsigned',
            'year_to' => 'smallint unsigned',
            'is_active' => 'tinyint(1)',
            'created_at' => 'timestamp',
            'updated_at' => 'timestamp',
        ], $columns);
    }

    public function test_rows_inserted_without_is_active_are_stored_active(): void
    {
        $makerId = DB::table('car_makers')->insertGetId(['name' => 'Alpha Motors']);

        DB::table('car_models')->insert([
            'car_maker_id' => $makerId,
            'name' => 'Roadster',
            'year_from' => 1955,
            'year_to' => 1957,
        ]);

        $this->assertDatabaseHas('car_makers', ['id' => $makerId, 'is_active' => true]);
        $this->assertDatabaseHas('car_models', ['car_maker_id' => $makerId, 'is_active' => true]);
    }

    public function test_new_instances_read_active_before_saving(): void
    {
        $maker = new CarMaker;
        $model = new CarModel;

        $this->assertTrue($maker->is_active);
        $this->assertTrue($model->is_active);
    }

    public function test_maker_is_active_is_not_mass_assignable(): void
    {
        $maker = new CarMaker;

        $maker->fill(['name' => 'Alpha Motors', 'is_active' => false]);

        $this->assertSame('Alpha Motors', $maker->name);
        $this->assertTrue($maker->is_active);
    }

    public function test_model_is_active_and_maker_id_are_not_mass_assignable(): void
    {
        $model = new CarModel;

        $model->fill(['name' => 'Roadster', 'year_from' => 1955, 'year_to' => 1957, 'car_maker_id' => 99, 'is_active' => false]);

        $this->assertSame('Roadster', $model->name);
        $this->assertNull($model->car_maker_id);
        $this->assertTrue($model->is_active);
    }

    public function test_creating_through_the_maker_sets_the_maker_id(): void
    {
        $maker = CarMaker::factory()->create();

        $model = $maker->models()->create(['name' => 'Roadster', 'year_from' => 1955, 'year_to' => 1957]);

        $this->assertSame($maker->id, $model->car_maker_id);
    }

    public function test_maker_names_are_unique(): void
    {
        CarMaker::factory()->create(['name' => 'Alpha Motors']);

        $this->expectException(QueryException::class);

        CarMaker::factory()->create(['name' => 'Alpha Motors']);
    }

    public function test_a_model_name_is_unique_within_its_maker(): void
    {
        $maker = CarMaker::factory()->create();
        CarModel::factory()->for($maker, 'maker')->create(['name' => 'Roadster']);

        $this->expectException(QueryException::class);

        CarModel::factory()->for($maker, 'maker')->create(['name' => 'Roadster']);
    }

    public function test_two_makers_may_each_have_a_model_with_the_same_name(): void
    {
        CarModel::factory()->create(['name' => 'Roadster']);

        $model = CarModel::factory()->create(['name' => 'Roadster']);

        $this->assertModelExists($model);
        $this->assertDatabaseCount('car_models', 2);
    }

    public function test_a_maker_with_models_cannot_be_deleted_until_its_models_are_gone(): void
    {
        $model = CarModel::factory()->create();
        $maker = $model->maker()->sole();

        try {
            $maker->delete();
            $this->fail('A maker with a model was deleted.');
        } catch (QueryException) {
        }
        $this->assertModelExists($maker);

        $model->delete();
        $maker->delete();

        $this->assertModelMissing($maker);
    }
}
