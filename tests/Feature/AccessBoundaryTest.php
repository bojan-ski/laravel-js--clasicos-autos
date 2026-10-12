<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AccessBoundaryTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_guest_opens_the_auth_pages(): void
    {
        $this->get(route('register'))->assertOk();
        $this->get(route('login'))->assertOk();
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function accountRoutes(): array
    {
        return [
            'GET profile' => ['get', 'profile'],
            'PUT profile password' => ['put', 'profile.password.update'],
            'DELETE profile' => ['delete', 'profile.destroy'],
            'GET admin account' => ['get', 'admin.account'],
            'PUT admin account password' => ['put', 'admin.account.password.update'],
        ];
    }

    #[DataProvider('accountRoutes')]
    public function test_guest_is_sent_to_login_from_every_account_route(string $method, string $route): void
    {
        $this->{$method}(route($route))->assertRedirect(route('login'));
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function forbiddenAccountRoutes(): array
    {
        return [
            'admin GET profile' => ['admin', 'get', 'profile'],
            'admin PUT profile password' => ['admin', 'put', 'profile.password.update'],
            'admin DELETE profile' => ['admin', 'delete', 'profile.destroy'],
            'appUser GET admin account' => ['appUser', 'get', 'admin.account'],
            'appUser PUT admin account password' => ['appUser', 'put', 'admin.account.password.update'],
        ];
    }

    #[DataProvider('forbiddenAccountRoutes')]
    public function test_the_other_role_gets_the_403_page_on_account_routes(string $role, string $method, string $route): void
    {
        $user = $role === 'admin' ? User::factory()->admin()->create() : User::factory()->create();

        $this->actingAs($user)
            ->{$method}(route($route))
            ->assertForbidden()
            ->assertSee(__('app.errors.403.title'));

        $this->assertModelExists($user);
    }

    public function test_app_user_opens_the_profile(): void
    {
        $user = User::factory()->create(['username' => 'ada lovelace']);

        $this->actingAs($user)
            ->get(route('profile'))
            ->assertOk()
            ->assertViewIs('pages.profile.show')
            ->assertViewHas('user', $user)
            ->assertSee(__('app.profile.heading', ['username' => 'ADA LOVELACE']));
    }

    public function test_admin_opens_the_account_page(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.account'))
            ->assertOk()
            ->assertViewIs('pages.admin.account')
            ->assertSee(__('app.admin.account.title'));
    }

    /**
     * @return array<string, array{string, string, string, string}>
     */
    public static function guestOnlyRequests(): array
    {
        $cases = [];

        foreach (['appUser' => 'profile', 'admin' => 'admin.account'] as $role => $landing) {
            $cases["{$role} GET login"] = [$role, 'get', 'login', $landing];
            $cases["{$role} POST login"] = [$role, 'post', 'login.store', $landing];
            $cases["{$role} GET register"] = [$role, 'get', 'register', $landing];
            $cases["{$role} POST register"] = [$role, 'post', 'register.store', $landing];
        }

        return $cases;
    }

    #[DataProvider('guestOnlyRequests')]
    public function test_signed_in_users_are_sent_from_guest_pages_to_their_landing(string $role, string $method, string $route, string $landing): void
    {
        $user = $role === 'admin' ? User::factory()->admin()->create() : User::factory()->create();

        $this->actingAs($user)
            ->{$method}(route($route))
            ->assertRedirect(route($landing));

        $this->assertDatabaseCount('users', 1);
    }
}
