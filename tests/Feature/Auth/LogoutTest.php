<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class LogoutTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_logout_ends_the_session_and_redirects_home_with_the_flash(): void
    {
        $this->actingAs(User::factory()->create());
        $this->withSession(['probe' => true]);
        $tokenBefore = session()->token();

        $this->post(route('logout'))
            ->assertRedirect(route('home'))
            ->assertSessionHas('success', __('app.auth.flash.logged_out'))
            ->assertSessionMissing('probe');

        $this->assertGuest();
        $this->assertNotSame($tokenBefore, session()->token());
    }

    public function test_logout_is_not_routed_for_get(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/logout')
            ->assertMethodNotAllowed();
    }
}
