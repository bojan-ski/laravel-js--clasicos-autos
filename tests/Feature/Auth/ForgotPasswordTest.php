<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ForgotPasswordTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const string HOSTILE_REFERER = 'https://evil.example/phish';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_guest_sees_step_one_with_the_email_field_and_step_two_inactive(): void
    {
        $response = $this->get(route('password.request'));

        $response->assertViewIs('pages.auth.forgot-password')
            ->assertSee('action="'.route('password.email').'"', false)
            ->assertSee('name="email" type="email"', false)
            ->assertSeeInOrder([
                __('app.auth.forgot.step_one'),
                __('app.auth.forgot.submit'),
                __('app.auth.forgot.step_two'),
                __('app.auth.forgot.step_two_hint'),
            ])
            ->assertDontSee('name="password"', false)
            ->assertSee('href="'.route('login').'"', false);
    }

    public function test_a_known_email_queues_one_reset_notification_behind_the_generic_flash(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->from(self::HOSTILE_REFERER)
            ->post(route('password.email'), ['email' => Str::upper($user->email)])
            ->assertRedirect(route('password.request'))
            ->assertSessionHas('success', 'If that address exists we sent a link')
            ->assertSessionHasNoErrors();

        Notification::assertSentToTimes($user, ResetPasswordNotification::class, 1);
        $this->assertDatabaseHas('password_reset_tokens', ['email' => $user->email]);
    }

    public function test_an_unknown_email_queues_nothing_behind_the_same_response(): void
    {
        Notification::fake();

        $this->post(route('password.email'), ['email' => 'nobody@example.com'])
            ->assertRedirect(route('password.request'))
            ->assertSessionHas('success', 'If that address exists we sent a link')
            ->assertSessionHasNoErrors();

        Notification::assertNothingSent();
        $this->assertDatabaseEmpty('password_reset_tokens');
    }

    public function test_a_second_request_within_a_minute_queues_nothing_and_keeps_the_first_link(): void
    {
        $this->freezeSecond();
        Notification::fake();
        $user = User::factory()->create();
        $firstToken = $this->requestLink($user);
        $this->travel(59)->seconds();

        $this->post(route('password.email'), ['email' => $user->email])
            ->assertRedirect(route('password.request'))
            ->assertSessionHas('success', 'If that address exists we sent a link')
            ->assertSessionHasNoErrors();

        Notification::assertSentToTimes($user, ResetPasswordNotification::class, 1);
        $this->get(route('password.reset', ['token' => $firstToken, 'email' => $user->email]))
            ->assertSee('name="password_confirmation"', false);
    }

    public function test_a_request_after_the_throttle_window_queues_a_new_link_and_kills_the_old_one(): void
    {
        $this->freezeSecond();
        Notification::fake();
        $user = User::factory()->create();
        $firstToken = $this->requestLink($user);
        $this->travel(61)->seconds();

        $this->post(route('password.email'), ['email' => $user->email])
            ->assertRedirect(route('password.request'));

        Notification::assertSentToTimes($user, ResetPasswordNotification::class, 2);
        $this->get(route('password.reset', ['token' => $firstToken, 'email' => $user->email]))
            ->assertSee(__('app.auth.reset.invalid'))
            ->assertDontSee('action="'.route('password.update').'"', false);
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function invalidEmails(): array
    {
        return [
            'empty' => [''],
            'malformed' => ['not-an-email'],
            'over 64 characters' => [str_repeat('a', 53).'@example.com'],
            'an array' => [['someone@example.com']],
        ];
    }

    #[DataProvider('invalidEmails')]
    public function test_an_invalid_email_fails_on_the_field_and_redirects_to_the_fixed_page(mixed $email): void
    {
        Notification::fake();

        $this->from(self::HOSTILE_REFERER)
            ->post(route('password.email'), ['email' => $email])
            ->assertRedirect(route('password.request'))
            ->assertInvalid(['email'])
            ->assertSessionMissing('success');

        Notification::assertNothingSent();
    }

    public function test_a_malformed_email_renders_its_error_inline_and_keeps_the_input(): void
    {
        $this->from(route('password.request'))
            ->post(route('password.email'), ['email' => 'not-an-email']);

        $this->get(route('password.request'))
            ->assertSee('aria-invalid="true" aria-describedby="email-error"', false)
            ->assertSee('id="email-error"', false)
            ->assertSee('value="not-an-email"', false);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function otherAccounts(): array
    {
        return [
            'admin' => ['admin'],
            'suspended user' => ['suspended'],
        ];
    }

    #[DataProvider('otherAccounts')]
    public function test_admins_and_suspended_users_also_receive_the_link(string $state): void
    {
        Notification::fake();
        $user = User::factory()->{$state}()->create();

        $this->post(route('password.email'), ['email' => $user->email])
            ->assertRedirect(route('password.request'));

        Notification::assertSentToTimes($user, ResetPasswordNotification::class, 1);
    }

    private function requestLink(User $user): string
    {
        $this->post(route('password.email'), ['email' => $user->email]);

        $token = null;
        Notification::assertSentTo($user, ResetPasswordNotification::class, function (ResetPasswordNotification $notification) use (&$token): bool {
            $token = $notification->token;

            return true;
        });

        return $token;
    }
}
