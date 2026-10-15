<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    protected function tearDown(): void
    {
        Cache::flush();

        parent::tearDown();
    }

    public function test_guest_sees_the_login_form(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk()->assertViewIs('pages.auth.login');

        foreach (['email', 'password', 'remember', 'submit', 'switch_prompt', 'reset_prompt', 'reset_link'] as $key) {
            $response->assertSee(__("app.auth.login.{$key}"));
        }

        $response->assertSee('action="'.route('login.store').'"', false)
            ->assertSee('name="remember" type="checkbox" value="1"', false)
            ->assertSee('href="'.route('register').'"', false)
            ->assertSee('href="'.route('password.request').'"', false);
    }

    public function test_login_marks_both_required_fields_and_explains_the_marker(): void
    {
        $content = (string) $this->get(route('login'))->getContent();

        $this->assertSame(2, substr_count($content, '<span aria-hidden="true">*</span>'));
        $this->assertStringContainsString('<p aria-hidden="true" class="text-xs text-muted">'.__('app.forms.required_note').'</p>', $content);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function landings(): array
    {
        return [
            'app user' => ['appUser', 'profile'],
            'admin' => ['admin', 'admin.users'],
        ];
    }

    #[DataProvider('landings')]
    public function test_valid_credentials_sign_in_and_land_on_the_role_landing(string $role, string $landing): void
    {
        $user = $role === 'admin' ? User::factory()->admin()->create() : User::factory()->create();
        $this->withSession(['probe' => true]);
        $sessionBefore = session()->getId();

        $this->post(route('login.store'), ['email' => strtoupper($user->email), 'password' => 'password'])
            ->assertRedirect(route($landing))
            ->assertSessionHas('success', __('app.auth.flash.logged_in'));

        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($sessionBefore, session()->getId());
    }

    public function test_a_stored_intended_url_is_ignored(): void
    {
        $user = User::factory()->create();

        $this->withSession(['url.intended' => route('legal.terms')])
            ->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('profile'));
    }

    public function test_remember_me_sets_the_remember_cookie_only_when_checked(): void
    {
        $user = User::factory()->create();
        $recaller = Auth::guard('web')->getRecallerName();

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password', 'remember' => '1'])
            ->assertCookie($recaller);

        $this->post(route('logout'));

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
            ->assertCookieMissing($recaller);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function badCredentials(): array
    {
        return [
            'wrong password' => ['ada@example.com', 'wrong-password'],
            'unknown email' => ['nobody@example.com', 'password'],
        ];
    }

    #[DataProvider('badCredentials')]
    public function test_bad_credentials_show_on_both_fields_and_redirect_to_login(string $email, string $password): void
    {
        User::factory()->create(['email' => 'ada@example.com']);

        $this->from('https://evil.example/phish')
            ->post(route('login.store'), ['email' => $email, 'password' => $password])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors([
                'email' => __('app.auth.login.failed'),
                'password' => __('app.auth.login.failed'),
            ]);

        $this->assertGuest();
    }

    public function test_the_sixth_attempt_within_a_minute_is_throttled_without_checking_credentials(): void
    {
        $this->freezeTime();
        $user = User::factory()->create();

        foreach (range(1, 5) as $attempt) {
            $this->post(route('login.store'), ['email' => $user->email, 'password' => 'wrong-password']);
        }

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['email' => __('auth.throttle', ['seconds' => 60, 'minutes' => 1])])
            ->assertSessionDoesntHaveErrors('password');

        $this->assertGuest();
    }

    public function test_a_successful_sign_in_clears_the_failure_counter(): void
    {
        $user = User::factory()->create();

        foreach (range(1, 4) as $attempt) {
            $this->post(route('login.store'), ['email' => $user->email, 'password' => 'wrong-password']);
        }

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);
        $this->post(route('logout'));

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'wrong-password']);
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('profile'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_a_suspended_user_with_an_end_date_is_refused_with_the_dated_notice(): void
    {
        $this->freezeTime();
        $user = User::factory()->suspended(now()->addDays(30))->create();
        $recaller = Auth::guard('web')->getRecallerName();

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password', 'remember' => '1'])
            ->assertRedirect(route('login'))
            ->assertCookieMissing($recaller)
            ->assertSessionHasErrors(['email' => __('app.auth.login.suspended_until', [
                'date' => now()->addDays(30)->format('d.m.Y'),
                'reason' => $user->suspension_reason,
            ])]);

        $this->assertGuest();
    }

    public function test_an_indefinitely_suspended_user_is_refused_with_the_indefinite_notice(): void
    {
        $user = User::factory()->suspended()->create();

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['email' => __('app.auth.login.suspended_indefinitely', ['reason' => $user->suspension_reason])]);

        $this->assertGuest();
    }

    /**
     * @return array<string, array{array<string, string>, string}>
     */
    public static function invalidInput(): array
    {
        return [
            'missing email' => [['password' => 'password'], 'email'],
            'malformed email' => [['email' => 'not-an-email', 'password' => 'password'], 'email'],
            'email of 65 characters' => [['email' => str_repeat('a', 53).'@example.com', 'password' => 'password'], 'email'],
            'missing password' => [['email' => 'ada@example.com'], 'password'],
        ];
    }

    /**
     * @param  array<string, string>  $input
     */
    #[DataProvider('invalidInput')]
    public function test_invalid_login_input_fails_on_the_field_without_counting_an_attempt(array $input, string $field): void
    {
        $this->from('https://evil.example/phish')
            ->post(route('login.store'), $input)
            ->assertRedirect(route('login'))
            ->assertInvalid([$field])
            ->assertSessionDoesntHaveErrors($field === 'email' ? 'password' : 'email');

        $this->assertSame(0, RateLimiter::attempts(Str::lower($input['email'] ?? '').'|127.0.0.1'));
        $this->assertGuest();
    }

    public function test_a_refused_suspended_attempt_neither_hits_nor_clears_the_limiter(): void
    {
        $user = User::factory()->suspended()->create();
        $key = $user->email.'|127.0.0.1';

        foreach (range(1, 4) as $attempt) {
            $this->post(route('login.store'), ['email' => $user->email, 'password' => 'wrong-password']);
        }

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors(['email' => __('app.auth.login.suspended_indefinitely', ['reason' => $user->suspension_reason])]);

        $this->assertSame(4, RateLimiter::attempts($key));
    }

    public function test_a_login_rehashes_a_password_hashed_with_outdated_options(): void
    {
        $user = User::factory()->create();
        $outdatedHash = Hash::make('password', ['rounds' => 5]);
        DB::table('users')->where('id', $user->id)->update(['password' => $outdatedHash]);

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('profile'));

        $this->assertNotSame($outdatedHash, $user->fresh()->password);
        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function expiredEnds(): array
    {
        return [
            'ended yesterday' => ['-1 day'],
            'ends this second' => ['+0 seconds'],
        ];
    }

    #[DataProvider('expiredEnds')]
    public function test_valid_credentials_lift_an_expired_suspension_and_sign_in(string $end): void
    {
        $this->freezeTime();
        $user = User::factory()->suspended(now()->modify($end))->create();

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('profile'));

        $this->assertAuthenticatedAs($user);
        $this->assertSame(
            ['status' => 'active', 'suspended_at' => null, 'suspended_until' => null, 'suspension_reason' => null],
            (array) DB::table('users')->where('id', $user->id)->first(['status', 'suspended_at', 'suspended_until', 'suspension_reason']),
        );
    }

    public function test_a_wrong_password_never_lifts_an_expired_suspension(): void
    {
        $user = User::factory()->suspended(now()->subDay())->create();

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'wrong-password'])
            ->assertSessionHasErrors(['email' => __('app.auth.login.failed')]);

        $this->assertGuest();
        $this->assertTrue($user->fresh()->isSuspended());
    }

    public function test_a_suspended_user_with_a_wrong_password_sees_only_bad_credentials(): void
    {
        $user = User::factory()->suspended()->create();

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'wrong-password'])
            ->assertSessionHasErrors([
                'email' => __('app.auth.login.failed'),
                'password' => __('app.auth.login.failed'),
            ]);

        $this->assertGuest();
    }
}
