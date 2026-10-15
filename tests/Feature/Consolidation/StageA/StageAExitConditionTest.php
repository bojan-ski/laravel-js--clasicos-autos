<?php

namespace Tests\Feature\Consolidation\StageA;

use App\Models\CarMaker;
use App\Models\CarModel;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class StageAExitConditionTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    /**
     * @return array<string, array{string}>
     */
    public static function publicPages(): array
    {
        return [
            'home' => ['home'],
            'privacy policy' => ['legal.privacy'],
            'terms' => ['legal.terms'],
            'cookie policy' => ['legal.cookies'],
        ];
    }

    #[DataProvider('publicPages')]
    public function test_a_guest_sees_the_postal_shell_on_each_public_page(string $route): void
    {
        $this->get(route($route))
            ->assertOk()
            ->assertSee('data-header-variant="guest"', false)
            ->assertSee('<main id="main"', false)
            ->assertSee('<footer', false)
            ->assertSee('data-cookie-banner', false);
    }

    public function test_a_guest_sees_the_postal_error_page_for_an_unknown_url(): void
    {
        $this->get('/no-such-page')
            ->assertNotFound()
            ->assertSee(__('app.errors.404.title'))
            ->assertSee(__('app.errors.404.stamp'))
            ->assertDontSee('data-header-variant', false);
    }

    public function test_a_visitor_registers_signs_in_resets_changes_and_deletes_the_account(): void
    {
        $this->freezeTime();
        Notification::fake();

        $this->post(route('register.store'), [
            'username' => 'rosa',
            'email' => 'rosa@example.com',
            'phone_number' => '+53 555 1234',
            'password' => 'first-pass',
            'password_confirmation' => 'first-pass',
            'terms_and_conditions' => '1',
            'privacy_policy' => '1',
        ])->assertRedirect(route('login'));
        $user = User::query()->where('email', 'rosa@example.com')->sole();

        $this->post(route('login.store'), ['email' => 'rosa@example.com', 'password' => 'first-pass', 'remember' => '1'])
            ->assertRedirect(route('profile'))
            ->assertCookie(Auth::guard('web')->getRecallerName());
        $this->assertAuthenticatedAs($user);
        $this->post(route('logout'))->assertRedirect(route('home'));

        foreach (range(1, 5) as $attempt) {
            $this->post(route('login.store'), ['email' => 'rosa@example.com', 'password' => 'wrong-pass']);
        }
        $this->post(route('login.store'), ['email' => 'rosa@example.com', 'password' => 'first-pass'])
            ->assertSessionHasErrors(['email' => __('auth.throttle', ['seconds' => 60, 'minutes' => 1])]);
        $this->assertGuest();
        $this->travel(61)->seconds();

        $this->post(route('password.email'), ['email' => 'rosa@example.com'])->assertRedirect(route('password.request'));
        $token = null;
        Notification::assertSentTo($user, ResetPasswordNotification::class, function (ResetPasswordNotification $notification) use (&$token): bool {
            $token = $notification->token;

            return true;
        });
        $this->post(route('password.update'), [
            'token' => $token,
            'email' => 'rosa@example.com',
            'password' => 'second-pass',
            'password_confirmation' => 'second-pass',
        ])->assertRedirect(route('login'));

        $this->post(route('login.store'), ['email' => 'rosa@example.com', 'password' => 'second-pass'])
            ->assertRedirect(route('profile'));

        $this->put(route('profile.password.update'), [
            'old_password' => 'second-pass',
            'password' => 'third-pass',
            'password_confirmation' => 'third-pass',
        ])->assertRedirect(route('profile'))->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('third-pass', $user->fresh()->password));

        $this->delete(route('profile.destroy'), ['delete_password' => 'third-pass'])->assertRedirect(route('home'));
        $this->assertGuest();
        $this->assertModelMissing($user);
    }

    public function test_the_admin_seeded_from_env_signs_in_and_lands_on_the_users_table(): void
    {
        config(['auth.admin.email' => 'boss@example.com', 'auth.admin.password' => 'initial1']);
        $this->seed(AdminUserSeeder::class);

        $this->post(route('login.store'), ['email' => 'boss@example.com', 'password' => 'initial1'])
            ->assertRedirect(route('admin.users'));
        $this->get(route('admin.users'))->assertOk()->assertSee('data-header-variant="admin"', false);
    }

    public function test_the_database_seeder_seeds_every_maker_and_model(): void
    {
        config(['auth.admin.email' => 'boss@example.com', 'auth.admin.password' => 'initial1']);
        $models = require database_path('seeders/data/car_models.php');

        $this->seed(DatabaseSeeder::class);

        $this->assertSame(37, CarMaker::query()->count());
        $this->assertSame(array_sum(array_map(count(...), $models)), CarModel::query()->count());
    }

    public function test_config_listings_holds_every_tunable(): void
    {
        foreach ([
            'min_year', 'max_year', 'images.max', 'images.max_upload_kb', 'images.webp_quality',
            'images.variants.full', 'images.variants.card', 'images.variants.thumb',
            'per_page.listings', 'per_page.advanced', 'per_page.profile', 'per_page.conversations', 'per_page.admin',
            'per_page.bookmarks', 'per_page.seller',
            'message.min', 'message.max', 'suspension.durations',
        ] as $key) {
            $this->assertTrue(config()->has("listings.{$key}"), "config('listings.{$key}') is missing");
        }
    }
}
