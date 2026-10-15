<?php

namespace Tests\Feature\Listings;

use App\Enums\ListingStatus;
use App\Models\CarListing;
use App\Models\CarMaker;
use App\Models\CarModel;
use App\Models\ListingImage;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ListingSchemaTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_car_listings_has_exactly_the_contract_columns(): void
    {
        $columns = collect(Schema::getColumns('car_listings'))->pluck('type', 'name')->all();

        $this->assertSame([
            'id' => 'bigint unsigned',
            'user_id' => 'bigint unsigned',
            'car_maker_id' => 'bigint unsigned',
            'car_model_id' => 'bigint unsigned',
            'name' => 'varchar(255)',
            'model' => 'varchar(255)',
            'description' => 'text',
            'year' => 'smallint unsigned',
            'mileage' => 'int unsigned',
            'price' => 'int unsigned',
            'transmission' => 'varchar(255)',
            'fuel_type' => 'varchar(255)',
            'engine_size' => 'varchar(50)',
            'engine_type' => 'varchar(100)',
            'engine_history' => 'varchar(255)',
            'engine_condition' => 'varchar(255)',
            'restoration_history' => 'varchar(255)',
            'original_parts_percentage' => 'tinyint unsigned',
            'body_type' => 'varchar(255)',
            'seat_material' => 'varchar(255)',
            'odometer' => 'varchar(255)',
            'license_plate_type' => 'varchar(255)',
            'documentation_status' => 'varchar(255)',
            'exterior_color' => 'varchar(50)',
            'exterior_condition' => 'varchar(255)',
            'interior_color' => 'varchar(50)',
            'interior_condition' => 'varchar(255)',
            'location_city' => 'varchar(100)',
            'location_state' => 'varchar(100)',
            'location_zipcode' => 'varchar(20)',
            'status' => 'varchar(255)',
            'submitted_at' => 'timestamp',
            'approved_at' => 'timestamp',
            'rejected_at' => 'timestamp',
            'rejection_reason' => 'varchar(1000)',
            'moderated_by' => 'bigint unsigned',
            'created_at' => 'timestamp',
            'updated_at' => 'timestamp',
        ], $columns);
    }

    public function test_car_listings_nullable_columns_are_exactly_the_optional_ones(): void
    {
        $nullable = collect(Schema::getColumns('car_listings'))->where('nullable', true)->pluck('name')->all();

        $this->assertSame([
            'car_model_id',
            'engine_size',
            'engine_type',
            'original_parts_percentage',
            'body_type',
            'seat_material',
            'license_plate_type',
            'exterior_color',
            'exterior_condition',
            'interior_color',
            'interior_condition',
            'approved_at',
            'rejected_at',
            'rejection_reason',
            'moderated_by',
            'created_at',
            'updated_at',
        ], $nullable);
    }

    public function test_listing_images_has_exactly_the_contract_columns(): void
    {
        $columns = collect(Schema::getColumns('listing_images'))->pluck('type', 'name')->all();
        $nullable = collect(Schema::getColumns('listing_images'))->where('nullable', true)->pluck('name')->all();

        $this->assertSame([
            'id' => 'bigint unsigned',
            'car_listing_id' => 'bigint unsigned',
            'position' => 'tinyint unsigned',
            'path' => 'varchar(255)',
            'card_path' => 'varchar(255)',
            'thumb_path' => 'varchar(255)',
            'created_at' => 'timestamp',
            'updated_at' => 'timestamp',
        ], $columns);
        $this->assertSame(['created_at', 'updated_at'], $nullable);
    }

    public function test_car_listings_has_the_two_composite_status_indexes_and_no_lone_status_index(): void
    {
        $indexes = collect(Schema::getIndexes('car_listings'))->reject(fn (array $index) => $index['primary'])->pluck('columns')->all();

        $this->assertContains(['status', 'submitted_at'], $indexes);
        $this->assertEqualsCanonicalizing([
            ['user_id'],
            ['car_maker_id'],
            ['car_model_id'],
            ['moderated_by'],
            ['status', 'created_at'],
            ['status', 'submitted_at'],
            ['year'],
            ['price'],
        ], $indexes);
    }

    public function test_listing_images_has_no_unique_index_on_position(): void
    {
        $indexes = collect(Schema::getIndexes('listing_images'))->reject(fn (array $index) => $index['primary']);

        $this->assertSame([['car_listing_id']], $indexes->pluck('columns')->all());
        $this->assertFalse($indexes->contains('unique', true));
    }

    public function test_a_row_inserted_without_status_or_submitted_at_is_pending_and_stamped_now(): void
    {
        $listing = CarListing::factory()->create();
        $row = collect($listing->getAttributes())->except(['id', 'status', 'submitted_at', 'created_at', 'updated_at'])->all();

        $id = DB::table('car_listings')->insertGetId($row);

        $stored = DB::table('car_listings')->find($id);
        $databaseNow = Carbon::parse(DB::scalar('select now()'));
        $this->assertSame('pending', $stored->status);
        $this->assertLessThan(60, abs(Carbon::parse($stored->submitted_at)->diffInSeconds($databaseNow)));
    }

    public function test_a_new_listing_reads_pending_before_saving(): void
    {
        $listing = new CarListing;

        $this->assertSame(ListingStatus::Pending, $listing->status);
    }

    public function test_server_controlled_columns_are_not_mass_assignable(): void
    {
        $listing = new CarListing;

        $listing->fill([
            'name' => 'Bel Air',
            'user_id' => 7,
            'status' => 'approved',
            'submitted_at' => now(),
            'approved_at' => now(),
            'rejected_at' => now(),
            'rejection_reason' => 'Blurry photos.',
            'moderated_by' => 3,
        ]);

        $this->assertSame(['status' => 'pending', 'name' => 'Bel Air'], $listing->getAttributes());
    }

    public function test_deleting_a_user_deletes_their_listings_and_those_listings_images(): void
    {
        $listing = CarListing::factory()->withImages(2)->create();
        $owner = $listing->owner()->sole();

        $owner->delete();

        $this->assertModelMissing($listing);
        $this->assertDatabaseCount('listing_images', 0);
    }

    public function test_deleting_a_listing_deletes_its_images_only(): void
    {
        $listing = CarListing::factory()->withImages(2)->create();
        $other = ListingImage::factory()->create();

        $listing->delete();

        $this->assertDatabaseCount('listing_images', 1);
        $this->assertModelExists($other);
    }

    public function test_a_maker_referenced_by_a_listing_cannot_be_deleted(): void
    {
        $maker = CarMaker::factory()->create();
        CarListing::factory()->customModel()->for($maker, 'maker')->create();

        $this->expectException(QueryException::class);

        $maker->delete();
    }

    public function test_a_model_referenced_by_a_listing_cannot_be_deleted(): void
    {
        $carModel = CarModel::factory()->create();
        CarListing::factory()->forCarModel($carModel)->create();

        $this->expectException(QueryException::class);

        $carModel->delete();
    }

    public function test_deleting_the_moderating_admin_keeps_the_listing_and_nulls_moderated_by(): void
    {
        $listing = CarListing::factory()->approved()->create();
        $admin = User::query()->findOrFail($listing->moderated_by);

        $admin->delete();

        $this->assertModelExists($listing);
        $this->assertNull($listing->fresh()->moderated_by);
    }
}
