<?php

namespace Tests\Feature;

use App\Models\CarListing;
use App\Models\CarMaker;
use App\Models\CarModel;
use App\Models\ContactMessage;
use App\Models\MessageReport;
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
            'GET admin users' => ['get', 'admin.users'],
            'PATCH admin users suspend' => ['patch', 'admin.users.suspend'],
            'PATCH admin users unsuspend' => ['patch', 'admin.users.unsuspend'],
            'DELETE admin users destroy' => ['delete', 'admin.users.destroy'],
            'GET admin listings' => ['get', 'admin.listings'],
            'PATCH admin listings promote' => ['patch', 'admin.listings.promote'],
            'GET admin reports' => ['get', 'admin.reports'],
            'PATCH admin reports resolve' => ['patch', 'admin.reports.resolve'],
            'GET admin feedback' => ['get', 'admin.feedback'],
            'PATCH admin feedback status' => ['patch', 'admin.feedback.status'],
            'GET admin makers' => ['get', 'admin.makers'],
            'POST admin makers store' => ['post', 'admin.makers.store'],
            'GET admin makers show' => ['get', 'admin.makers.show'],
            'PATCH admin makers update' => ['patch', 'admin.makers.update'],
            'PATCH admin makers active' => ['patch', 'admin.makers.active'],
            'DELETE admin makers destroy' => ['delete', 'admin.makers.destroy'],
            'POST admin makers models store' => ['post', 'admin.makers.models.store'],
            'PATCH admin makers models update' => ['patch', 'admin.makers.models.update'],
            'PATCH admin makers models active' => ['patch', 'admin.makers.models.active'],
            'DELETE admin makers models destroy' => ['delete', 'admin.makers.models.destroy'],
            'GET contact' => ['get', 'contact'],
            'POST contact' => ['post', 'contact.store'],
        ];
    }

    #[DataProvider('accountRoutes')]
    public function test_guest_is_sent_to_login_from_every_account_route(string $method, string $route): void
    {
        $this->{$method}($this->urlFor($route))->assertRedirect(route('login'));
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
            'appUser GET admin users' => ['appUser', 'get', 'admin.users'],
            'appUser PATCH admin users suspend' => ['appUser', 'patch', 'admin.users.suspend'],
            'appUser PATCH admin users unsuspend' => ['appUser', 'patch', 'admin.users.unsuspend'],
            'appUser DELETE admin users destroy' => ['appUser', 'delete', 'admin.users.destroy'],
            'appUser GET admin listings' => ['appUser', 'get', 'admin.listings'],
            'appUser PATCH admin listings promote' => ['appUser', 'patch', 'admin.listings.promote'],
            'appUser GET admin reports' => ['appUser', 'get', 'admin.reports'],
            'appUser PATCH admin reports resolve' => ['appUser', 'patch', 'admin.reports.resolve'],
            'appUser GET admin feedback' => ['appUser', 'get', 'admin.feedback'],
            'appUser PATCH admin feedback status' => ['appUser', 'patch', 'admin.feedback.status'],
            'appUser GET admin makers' => ['appUser', 'get', 'admin.makers'],
            'appUser POST admin makers store' => ['appUser', 'post', 'admin.makers.store'],
            'appUser GET admin makers show' => ['appUser', 'get', 'admin.makers.show'],
            'appUser PATCH admin makers update' => ['appUser', 'patch', 'admin.makers.update'],
            'appUser PATCH admin makers active' => ['appUser', 'patch', 'admin.makers.active'],
            'appUser DELETE admin makers destroy' => ['appUser', 'delete', 'admin.makers.destroy'],
            'appUser POST admin makers models store' => ['appUser', 'post', 'admin.makers.models.store'],
            'appUser PATCH admin makers models update' => ['appUser', 'patch', 'admin.makers.models.update'],
            'appUser PATCH admin makers models active' => ['appUser', 'patch', 'admin.makers.models.active'],
            'appUser DELETE admin makers models destroy' => ['appUser', 'delete', 'admin.makers.models.destroy'],
            'admin GET contact' => ['admin', 'get', 'contact'],
            'admin POST contact' => ['admin', 'post', 'contact.store'],
        ];
    }

    #[DataProvider('forbiddenAccountRoutes')]
    public function test_the_other_role_gets_the_403_page_on_account_routes(string $role, string $method, string $route): void
    {
        $user = $role === 'admin' ? User::factory()->admin()->create() : User::factory()->create();

        $this->actingAs($user)
            ->{$method}($this->urlFor($route))
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
     * @return array<string, array{string, string, string, array<string, string>, string}>
     */
    public static function guestOnlyRequests(): array
    {
        $cases = [];

        foreach (['appUser' => 'profile', 'admin' => 'admin.users'] as $role => $landing) {
            $cases["{$role} GET login"] = [$role, 'get', 'login', [], $landing];
            $cases["{$role} POST login"] = [$role, 'post', 'login.store', [], $landing];
            $cases["{$role} GET register"] = [$role, 'get', 'register', [], $landing];
            $cases["{$role} POST register"] = [$role, 'post', 'register.store', [], $landing];
            $cases["{$role} GET forgot password"] = [$role, 'get', 'password.request', [], $landing];
            $cases["{$role} POST forgot password"] = [$role, 'post', 'password.email', [], $landing];
            $cases["{$role} GET reset password"] = [$role, 'get', 'password.reset', ['token' => 'some-token', 'email' => 'someone@example.com'], $landing];
            $cases["{$role} POST reset password"] = [$role, 'post', 'password.update', [], $landing];
        }

        return $cases;
    }

    /**
     * @param  array<string, string>  $parameters
     */
    #[DataProvider('guestOnlyRequests')]
    public function test_signed_in_users_are_sent_from_guest_pages_to_their_landing(string $role, string $method, string $route, array $parameters, string $landing): void
    {
        $user = $role === 'admin' ? User::factory()->admin()->create() : User::factory()->create();

        $this->actingAs($user)
            ->{$method}(route($route, $parameters))
            ->assertRedirect(route($landing));

        $this->assertDatabaseCount('users', 1);
    }

    private function urlFor(string $route): string
    {
        return match (true) {
            str_starts_with($route, 'admin.users.') => route($route, User::factory()->create()),
            str_starts_with($route, 'admin.listings.') => route($route, CarListing::factory()->customModel()->create()),
            str_starts_with($route, 'admin.reports.') => route($route, MessageReport::factory()->create()),
            str_starts_with($route, 'admin.feedback.') => route($route, ContactMessage::factory()->create()),
            in_array($route, ['admin.makers.models.update', 'admin.makers.models.active', 'admin.makers.models.destroy'], true) => (function () use ($route): string {
                $model = CarModel::factory()->create();

                return route($route, ['maker' => $model->car_maker_id, 'model' => $model->id]);
            })(),
            str_starts_with($route, 'admin.makers.') && $route !== 'admin.makers.store' => route($route, CarMaker::factory()->create()),
            default => route($route),
        };
    }
}
