<?php

namespace Tests\Feature\ReferenceData;

use App\Enums\UserRole;
use App\Models\CarMaker;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_a_full_seed_creates_the_admin_the_makers_and_their_models(): void
    {
        config(['auth.admin.email' => 'boss@example.com', 'auth.admin.password' => 'initial1']);

        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseHas('users', ['email' => 'boss@example.com', 'role' => UserRole::Admin->value]);
        $this->assertDatabaseCount('car_makers', 37);
        $this->assertFalse(CarMaker::query()->doesntHave('models')->exists());
    }
}
