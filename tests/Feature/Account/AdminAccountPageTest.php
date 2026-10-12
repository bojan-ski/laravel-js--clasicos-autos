<?php

namespace Tests\Feature\Account;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class AdminAccountPageTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_admin_account_shows_the_password_form_only(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.account'))
            ->assertOk()
            ->assertSeeInOrder([
                __('app.admin.account.kicker'),
                __('app.admin.account.title'),
                __('app.account.password.title'),
                'action="'.route('admin.account.password.update').'"',
                'name="_method" value="PUT"',
                __('app.account.password.submit'),
            ], false)
            ->assertDontSee(__('app.profile.details.username'))
            ->assertDontSee(__('app.profile.details.created_at'))
            ->assertDontSee('<dialog', false)
            ->assertDontSee('action="'.route('profile.destroy').'"', false);
    }
}
