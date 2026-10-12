<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class ResetPasswordTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const string HOSTILE_REFERER = 'https://evil.example/phish';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_a_valid_link_shows_the_email_as_text_and_the_new_password_step(): void
    {
        $user = User::factory()->create(['email' => 'ana@example.com']);
        $token = $this->requestLink($user);

        $response = $this->get(route('password.reset', ['token' => $token, 'email' => $user->email]));

        $response->assertViewIs('pages.auth.reset-password')
            ->assertSee('action="'.route('password.update').'"', false)
            ->assertSee('<input type="hidden" name="token" value="'.$token.'">', false)
            ->assertSee('<input type="hidden" name="email" value="ana@example.com" autocomplete="username">', false)
            ->assertSee('<dd class="text-right break-all text-ink">ana@example.com</dd>', false)
            ->assertDontSee('name="email" type="email"', false)
            ->assertSee('name="password" type="password"', false)
            ->assertSee('name="password_confirmation" type="password"', false)
            ->assertSee(__('app.auth.reset.submit'))
            ->assertSee('<title>'.__('app.auth.reset.title').' — ', false)
            ->assertDontSee(__('app.auth.reset.invalid'));
    }

    #[TestWith(['valid'])]
    #[TestWith(['invalid'])]
    public function test_the_link_page_sends_no_referrer_and_is_never_stored(string $state): void
    {
        $user = User::factory()->create();
        $token = $state === 'valid' ? $this->requestLink($user) : 'junk-token';

        $response = $this->get(route('password.reset', ['token' => $token, 'email' => $user->email]));

        $response->assertOk()->assertHeader('Referrer-Policy', 'no-referrer');
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function test_an_expired_link_renders_the_invalid_state(): void
    {
        $user = User::factory()->create();
        $token = $this->requestLink($user);
        $this->travel(61)->minutes();

        $response = $this->get(route('password.reset', ['token' => $token, 'email' => $user->email]));

        $this->assertInvalidLinkPage($response);
    }

    public function test_a_wrong_token_renders_the_invalid_state(): void
    {
        $user = User::factory()->create();
        $this->requestLink($user);

        $response = $this->get(route('password.reset', ['token' => 'not-the-token', 'email' => $user->email]));

        $this->assertInvalidLinkPage($response);
    }

    public function test_another_users_email_renders_the_invalid_state(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $token = $this->requestLink($user);

        $response = $this->get(route('password.reset', ['token' => $token, 'email' => $other->email]));

        $this->assertInvalidLinkPage($response);
    }

    public function test_a_link_without_an_email_renders_the_invalid_state(): void
    {
        $user = User::factory()->create();
        $token = $this->requestLink($user);

        $response = $this->get(route('password.reset', ['token' => $token]));

        $this->assertInvalidLinkPage($response);
    }

    public function test_a_used_link_renders_the_invalid_state(): void
    {
        $user = User::factory()->create();
        $token = $this->requestLink($user);
        $this->post(route('password.update'), $this->resetPayload($token, $user->email));

        $response = $this->get(route('password.reset', ['token' => $token, 'email' => $user->email]));

        $this->assertInvalidLinkPage($response);
    }

    public function test_the_link_check_takes_at_least_the_timebox_for_an_unknown_email(): void
    {
        User::factory()->create();
        $this->get(route('password.request'));
        $start = hrtime(true);

        $this->get(route('password.reset', ['token' => 'some-token', 'email' => 'nobody@example.com']));

        $this->assertGreaterThanOrEqual(400_000_000, hrtime(true) - $start);
    }

    #[TestWith(['another user'])]
    #[TestWith(['expired'])]
    #[TestWith(['replaced'])]
    public function test_a_dead_link_submission_returns_to_its_page_and_writes_nothing(string $case): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $token = $this->requestLink($user);
        $email = $case === 'another user' ? $other->email : $user->email;
        if ($case === 'expired') {
            $this->travel(61)->minutes();
        }
        if ($case === 'replaced') {
            $this->travel(61)->seconds();
            $this->post(route('password.email'), ['email' => $user->email]);
        }

        $this->post(route('password.update'), $this->resetPayload($token, $email))
            ->assertRedirect(route('password.reset', ['token' => $token, 'email' => $email]))
            ->assertSessionMissing('success')
            ->assertSessionHasNoErrors();

        $this->assertSame($user->password, $user->fresh()->password);
        $this->assertSame($other->password, $other->fresh()->password);
        $this->assertGuest();
    }

    public function test_a_valid_reset_stores_the_new_password_and_sends_the_guest_to_login(): void
    {
        $user = User::factory()->create(['remember_token' => 'old-remember-token']);
        $token = $this->requestLink($user);

        $this->from(self::HOSTILE_REFERER)
            ->post(route('password.update'), $this->resetPayload($token, $user->email))
            ->assertRedirect(route('login'))
            ->assertSessionHas('success', 'Your password has been reset.')
            ->assertSessionHasNoErrors();

        $fresh = $user->fresh();
        $this->assertTrue(Hash::check('new-secret', $fresh->password));
        $this->assertNotSame('old-remember-token', $fresh->remember_token);
        $this->assertSame(60, strlen($fresh->remember_token));
        $this->assertSame($user->role, $fresh->role);
        $this->assertSame($user->status, $fresh->status);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
        $this->assertGuest();
    }

    public function test_submitting_a_used_link_again_returns_to_its_page_and_keeps_the_new_password(): void
    {
        $user = User::factory()->create();
        $token = $this->requestLink($user);
        $this->post(route('password.update'), $this->resetPayload($token, $user->email));
        $hash = $user->fresh()->password;

        $this->post(route('password.update'), $this->resetPayload($token, $user->email, 'another-secret'))
            ->assertRedirect(route('password.reset', ['token' => $token, 'email' => $user->email]))
            ->assertSessionMissing('success')
            ->assertSessionHasNoErrors();

        $this->assertSame($hash, $user->fresh()->password);
        $this->assertGuest();
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function invalidPasswords(): array
    {
        return [
            'too short' => [['password' => 'short', 'password_confirmation' => 'short']],
            'unconfirmed' => [['password_confirmation' => 'other-secret']],
            'missing' => [['password' => '', 'password_confirmation' => '']],
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    #[DataProvider('invalidPasswords')]
    public function test_an_invalid_password_returns_to_the_same_link_and_keeps_password_and_token(array $overrides): void
    {
        $user = User::factory()->create();
        $token = $this->requestLink($user);
        $hash = $user->password;

        $this->from(self::HOSTILE_REFERER)
            ->post(route('password.update'), [...$this->resetPayload($token, $user->email), ...$overrides])
            ->assertRedirect(route('password.reset', ['token' => $token, 'email' => $user->email]))
            ->assertInvalid(['password'])
            ->assertSessionMissing('success')
            ->assertSessionMissing('_old_input.token')
            ->assertSessionMissing('_old_input.password');

        $this->assertSame($hash, $user->fresh()->password);
        $this->assertDatabaseHas('password_reset_tokens', ['email' => $user->email]);
    }

    public function test_a_password_error_renders_inline_on_the_link_page(): void
    {
        $user = User::factory()->create();
        $token = $this->requestLink($user);
        $this->post(route('password.update'), [
            ...$this->resetPayload($token, $user->email),
            'password' => 'short',
            'password_confirmation' => 'short',
        ]);

        $this->get(route('password.reset', ['token' => $token, 'email' => $user->email]))
            ->assertSee('aria-invalid="true" aria-describedby="password-hint password-error"', false)
            ->assertSee('<p id="password-hint" class="text-xs text-muted">'.__('app.account.password.password_placeholder').'</p>', false)
            ->assertSee('id="password-error"', false)
            ->assertDontSee('value="short"', false);
    }

    #[TestWith(['not-an-email'])]
    #[TestWith(['aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa@example.com'])]
    public function test_an_invalid_email_returns_to_the_link_page_with_an_email_error(string $email): void
    {
        $user = User::factory()->create();
        $token = $this->requestLink($user);
        $hash = $user->password;

        $this->from(self::HOSTILE_REFERER)
            ->post(route('password.update'), $this->resetPayload($token, $email))
            ->assertRedirect(route('password.reset', ['token' => $token, 'email' => $email]))
            ->assertInvalid(['email'])
            ->assertSessionDoesntHaveErrors('password');

        $this->assertSame($hash, $user->fresh()->password);
    }

    public function test_a_submission_without_a_token_redirects_to_the_request_page(): void
    {
        $user = User::factory()->create();
        $hash = $user->password;

        $this->from(self::HOSTILE_REFERER)
            ->post(route('password.update'), $this->resetPayload('', $user->email))
            ->assertRedirect(route('password.request'))
            ->assertInvalid(['token']);

        $this->assertSame($hash, $user->fresh()->password);
    }

    public function test_an_admin_resets_the_password_and_signs_in_with_it(): void
    {
        $admin = User::factory()->admin()->create();
        $token = $this->requestLink($admin);

        $this->post(route('password.update'), $this->resetPayload($token, $admin->email))
            ->assertRedirect(route('login'));

        $this->post(route('login.store'), ['email' => $admin->email, 'password' => 'new-secret'])
            ->assertRedirect(route('admin.account'));
        $this->assertAuthenticatedAs($admin);
    }

    public function test_a_suspended_user_resets_the_password_but_login_still_refuses_them(): void
    {
        $user = User::factory()->suspended()->create();
        $token = $this->requestLink($user);

        $this->post(route('password.update'), $this->resetPayload($token, $user->email))
            ->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('new-secret', $user->fresh()->password));
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'new-secret'])
            ->assertSessionHasErrors(['email' => __('app.auth.login.suspended_indefinitely', ['reason' => $user->suspension_reason])]);
        $this->assertGuest();
    }

    private function requestLink(User $user): string
    {
        Notification::fake();

        $this->post(route('password.email'), ['email' => $user->email]);

        $token = null;
        Notification::assertSentTo($user, ResetPasswordNotification::class, function (ResetPasswordNotification $notification) use (&$token): bool {
            $token = $notification->token;

            return true;
        });

        return $token;
    }

    /**
     * @return array<string, string>
     */
    private function resetPayload(string $token, string $email, string $password = 'new-secret'): array
    {
        return [
            'token' => $token,
            'email' => $email,
            'password' => $password,
            'password_confirmation' => $password,
        ];
    }

    private function assertInvalidLinkPage(TestResponse $response): void
    {
        $response->assertOk()
            ->assertSee('<title>'.__('app.auth.reset.invalid_title').' — ', false)
            ->assertSee('<p role="alert" class="font-heading text-[26px] leading-tight text-red-deep italic">'.__('app.auth.reset.invalid').'</p>', false)
            ->assertSee('href="'.route('password.request').'"', false)
            ->assertSee(__('app.auth.reset.request_new'))
            ->assertDontSee('action="'.route('password.update').'"', false)
            ->assertDontSee('name="password"', false)
            ->assertDontSee(__('app.auth.forgot.step_two'));
    }
}
