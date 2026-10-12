<?php

namespace Tests\Feature\Account;

use App\Models\User;
use App\Services\AccountDeletionService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use LogicException;
use Tests\TestCase;

class AccountDeletionServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_it_deletes_an_app_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        app(AccountDeletionService::class)->delete($user);

        $this->assertModelMissing($user);
        $this->assertModelExists($other);
    }

    public function test_it_refuses_to_delete_an_admin(): void
    {
        $admin = User::factory()->admin()->create();

        try {
            app(AccountDeletionService::class)->delete($admin);
            $this->fail('An admin account was deleted.');
        } catch (LogicException) {
            $this->assertModelExists($admin);
        }
    }
}
