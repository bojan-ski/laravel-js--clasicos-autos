<?php

namespace Tests\Feature;

use App\Enums\NavAudience;
use App\Models\User;
use App\Support\HeaderAction;
use App\Support\MainNavigation;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class HeaderActionsTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_the_audience_follows_the_role(): void
    {
        $this->assertSame(NavAudience::Guest, NavAudience::forUser(null));
        $this->assertSame(NavAudience::Member, NavAudience::forUser(User::factory()->make()));
        $this->assertSame(NavAudience::Admin, NavAudience::forUser(User::factory()->admin()->make()));
    }

    public function test_each_audience_gets_its_action_set_in_order(): void
    {
        $navigation = app(MainNavigation::class);

        $this->assertSame(['sign-in', 'register'], $this->keys($navigation->actionsFor(NavAudience::Guest, null)));
        $this->assertSame(['list-car', 'profile', 'log-out'], $this->keys($navigation->actionsFor(NavAudience::Member, User::factory()->make())));
        $this->assertSame(['account', 'log-out'], $this->keys($navigation->actionsFor(NavAudience::Admin, User::factory()->admin()->make())));
    }

    public function test_guest_header_links_to_sign_in_and_register(): void
    {
        $this->get(route('home'))
            ->assertSee('href="'.route('login').'"', false)
            ->assertSee('href="'.route('register').'"', false)
            ->assertSee(__('app.header.sign_in'))
            ->assertDontSee('action="'.route('logout').'"', false);
    }

    public function test_member_header_shows_the_initials_avatar_and_a_log_out_form(): void
    {
        $this->actingAs(User::factory()->make(['username' => 'ángel ruiz']))
            ->get(route('home'))
            ->assertSeeInOrder(['href="'.route('profile').'"', 'ÁR', '<span class="sr-only">'.__('app.header.profile').'</span>'], false)
            ->assertSee('<form method="POST" action="'.route('logout').'"', false)
            ->assertSee(__('app.header.log_out'))
            ->assertDontSee('href="'.route('login').'"', false);
    }

    public function test_admin_header_has_the_account_link_then_the_log_out_form(): void
    {
        $this->actingAs(User::factory()->admin()->make())
            ->get(route('home'))
            ->assertSee('data-header-variant="admin"', false)
            ->assertSeeInOrder(['href="'.route('admin.account').'"', __('app.header.account'), '<form method="POST" action="'.route('logout').'"'], false)
            ->assertSee('text-cream-on-dark hover:bg-cream-on-dark/10', false)
            ->assertDontSee('href="'.route('profile').'"', false)
            ->assertDontSee('href="'.route('login').'"', false);
    }

    /**
     * @param  list<HeaderAction>  $actions
     * @return list<string>
     */
    private function keys(array $actions): array
    {
        return array_column($actions, 'key');
    }
}
