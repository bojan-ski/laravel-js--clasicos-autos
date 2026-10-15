<?php

namespace Tests\Unit;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class UserTest extends TestCase
{
    /**
     * @return array<string, array{string, string}>
     */
    public static function usernames(): array
    {
        return [
            'one word' => ['bo', 'BO'],
            'one long word' => ['cadillac', 'CA'],
            'two words' => ['ada lovelace', 'AL'],
            'three words use the first two' => ['juan carlos perez', 'JC'],
            'separators' => ['el-gran_coche.57', 'EG'],
            'non-ASCII' => ['ángel ruiz', 'ÁR'],
            'single character' => ['x', 'X'],
        ];
    }

    #[DataProvider('usernames')]
    public function test_initials(string $username, string $expected): void
    {
        $this->assertSame($expected, (new User(['username' => $username]))->initials());
    }

    public function test_a_new_user_is_an_active_app_user_landing_on_the_profile(): void
    {
        $user = new User;

        $this->assertTrue($user->isAppUser());
        $this->assertFalse($user->isAdmin());
        $this->assertFalse($user->isSuspended());
        $this->assertSame('profile', $user->landingRoute());
    }

    public function test_an_admin_lands_on_the_admin_account(): void
    {
        $user = new User;
        $user->role = UserRole::Admin;

        $this->assertTrue($user->isAdmin());
        $this->assertFalse($user->isAppUser());
        $this->assertSame('admin.account', $user->landingRoute());
    }

    public function test_a_suspended_user_is_suspended(): void
    {
        $user = new User;
        $user->status = UserStatus::Suspended;

        $this->assertTrue($user->isSuspended());
    }

    /**
     * @return array<string, array{UserRole, UserStatus, bool}>
     */
    public static function activeAppUsers(): array
    {
        return [
            'active app user' => [UserRole::AppUser, UserStatus::Active, true],
            'suspended app user' => [UserRole::AppUser, UserStatus::Suspended, false],
            'active admin' => [UserRole::Admin, UserStatus::Active, false],
            'suspended admin' => [UserRole::Admin, UserStatus::Suspended, false],
        ];
    }

    #[DataProvider('activeAppUsers')]
    public function test_only_an_unsuspended_app_user_is_an_active_app_user(UserRole $role, UserStatus $status, bool $expected): void
    {
        $user = new User;
        $user->role = $role;
        $user->status = $status;

        $this->assertSame($expected, $user->isActiveAppUser());
    }
}
