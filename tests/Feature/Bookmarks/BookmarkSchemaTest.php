<?php

namespace Tests\Feature\Bookmarks;

use App\Models\CarListing;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BookmarkSchemaTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_user_bookmarks_has_exactly_the_contract_columns(): void
    {
        $columns = collect(Schema::getColumns('user_bookmarks'))->pluck('type', 'name')->all();

        $this->assertSame([
            'user_id' => 'bigint unsigned',
            'car_listing_id' => 'bigint unsigned',
            'created_at' => 'timestamp',
            'updated_at' => 'timestamp',
        ], $columns);
    }

    public function test_the_primary_key_is_the_user_and_listing_pair(): void
    {
        $primary = collect(Schema::getIndexes('user_bookmarks'))->firstWhere('primary', true);

        $this->assertSame(['user_id', 'car_listing_id'], $primary['columns']);
    }

    public function test_the_same_pair_cannot_be_stored_twice(): void
    {
        $user = User::factory()->create();
        $listing = CarListing::factory()->approved()->create();
        DB::table('user_bookmarks')->insert(['user_id' => $user->id, 'car_listing_id' => $listing->id]);

        $this->expectException(UniqueConstraintViolationException::class);

        DB::table('user_bookmarks')->insert(['user_id' => $user->id, 'car_listing_id' => $listing->id]);
    }

    public function test_deleting_a_user_removes_their_bookmarks(): void
    {
        $user = User::factory()->create();
        $kept = User::factory()->create();
        $listing = CarListing::factory()->approved()->create();
        $user->bookmarks()->attach($listing->id);
        $kept->bookmarks()->attach($listing->id);

        $user->delete();

        $this->assertSame([$kept->id], DB::table('user_bookmarks')->pluck('user_id')->all());
    }

    public function test_deleting_a_listing_removes_its_bookmarks(): void
    {
        $user = User::factory()->create();
        [$deleted, $kept] = CarListing::factory()->approved()->count(2)->create();
        $user->bookmarks()->attach([$deleted->id, $kept->id]);

        $deleted->delete();

        $this->assertSame([$kept->id], DB::table('user_bookmarks')->pluck('car_listing_id')->all());
    }
}
