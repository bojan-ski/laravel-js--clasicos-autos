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

    public function test_guest_opens_the_auth_pages_and_is_sent_to_login_from_the_landings(): void
    {
        $this->get(route('register'))->assertOk();
        $this->get(route('login'))->assertOk();
        $this->get(route('profile'))->assertRedirect(route('login'));
        $this->get(route('admin.account'))->assertRedirect(route('login'));
    }

    public function test_app_user_opens_the_profile_stub_and_is_forbidden_from_the_admin_area(): void
    {
        $user = User::factory()->create(['username' => 'ada lovelace']);

        $this->actingAs($user)
            ->get(route('profile'))
            ->assertOk()
            ->assertViewIs('pages.profile.show')
            ->assertViewHas('user', $user)
            ->assertSee(__('app.profile.heading', ['username' => 'ada lovelace']));

        $this->get(route('admin.account'))
            ->assertForbidden()
            ->assertSee(__('app.errors.403.title'));
    }

    public function test_admin_opens_the_account_stub_and_is_forbidden_from_the_profile(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.account'))
            ->assertOk()
            ->assertViewIs('pages.admin.account')
            ->assertSee(__('app.admin.account.title'));

        $this->get(route('profile'))
            ->assertForbidden()
            ->assertSee(__('app.errors.403.title'));
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
