<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class AdminUserSeederTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_database_seeder_creates_one_active_admin_from_config(): void
    {
        config(['auth.admin.email' => 'Boss@Example.com', 'auth.admin.password' => 'initial1']);

        $this->seed(DatabaseSeeder::class);

        $admin = User::query()->sole();
        $this->assertSame('boss@example.com', $admin->email);
        $this->assertSame('admin user', $admin->username);
        $this->assertSame('123456789', $admin->phone_number);
        $this->assertSame(UserRole::Admin, $admin->role);
        $this->assertSame(UserStatus::Active, $admin->status);
        $this->assertTrue(Hash::check('initial1', $admin->password));
    }

    public function test_a_second_run_leaves_the_existing_admin_untouched(): void
    {
        config(['auth.admin.email' => 'boss@example.com', 'auth.admin.password' => 'initial1']);
        $this->seed(AdminUserSeeder::class);

        config(['auth.admin.password' => 'changed2']);
        $this->seed(AdminUserSeeder::class);

        $this->assertDatabaseCount('users', 1);
        $this->assertTrue(Hash::check('initial1', User::query()->sole()->password));
    }

    public function test_an_app_user_with_the_admin_email_is_not_promoted(): void
    {
        $user = User::factory()->create(['email' => 'boss@example.com']);
        config(['auth.admin.email' => 'boss@example.com', 'auth.admin.password' => 'initial1']);

        $this->seed(AdminUserSeeder::class);

        $this->assertDatabaseCount('users', 1);
        $this->assertSame(UserRole::AppUser, $user->fresh()->role);
    }

    /**
     * @return array<string, array{?string, ?string}>
     */
    public static function missingCredentials(): array
    {
        return [
            'missing email' => [null, 'initial1'],
            'missing password' => ['boss@example.com', null],
            'short password' => ['boss@example.com', 'five5'],
        ];
    }

    #[DataProvider('missingCredentials')]
    public function test_it_refuses_to_seed_without_valid_credentials(?string $email, ?string $password): void
    {
        config(['auth.admin.email' => $email, 'auth.admin.password' => $password]);

        try {
            $this->seed(AdminUserSeeder::class);
            $this->fail('The seeder ran without valid admin credentials.');
        } catch (RuntimeException) {
        }

        $this->assertDatabaseCount('users', 0);
    }
}
