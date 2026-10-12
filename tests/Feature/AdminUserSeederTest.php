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
     * @return array<string, array{?string, ?string, string}>
     */
    public static function missingCredentials(): array
    {
        $noEmail = 'ADMIN_EMAIL is not set; refusing to seed the admin.';
        $badPassword = 'ADMIN_PASSWORD is missing or shorter than 6 characters; refusing to seed the admin.';

        return [
            'missing email' => [null, 'initial1', $noEmail],
            'missing password' => ['boss@example.com', null, $badPassword],
            'short password' => ['boss@example.com', 'five5', $badPassword],
        ];
    }

    #[DataProvider('missingCredentials')]
    public function test_it_refuses_to_seed_without_valid_credentials(?string $email, ?string $password, string $message): void
    {
        config(['auth.admin.email' => $email, 'auth.admin.password' => $password]);

        $refusal = null;

        try {
            $this->seed(AdminUserSeeder::class);
        } catch (RuntimeException $exception) {
            $refusal = $exception;
        }

        $this->assertNotNull($refusal, 'The seeder ran without valid admin credentials.');
        $this->assertSame($message, $refusal->getMessage());
        $this->assertDatabaseCount('users', 0);
    }

    public function test_it_refuses_with_a_clear_message_when_the_admin_username_is_taken(): void
    {
        User::factory()->create(['username' => 'admin user']);
        config(['auth.admin.email' => 'boss@example.com', 'auth.admin.password' => 'initial1']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The username "admin user" is already taken; refusing to seed the admin.');

        $this->seed(AdminUserSeeder::class);
    }
}
