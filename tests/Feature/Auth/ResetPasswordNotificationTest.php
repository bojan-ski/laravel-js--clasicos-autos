<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ResetPasswordNotificationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_the_user_sends_the_project_notification_with_the_token(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $user->sendPasswordResetNotification('plain-token');

        Notification::assertSentTo($user, ResetPasswordNotification::class, fn (ResetPasswordNotification $notification): bool => $notification->token === 'plain-token');
    }

    public function test_the_notification_is_queued_with_three_tries_and_a_thirty_second_timeout(): void
    {
        Queue::fake([SendQueuedNotifications::class]);
        $user = User::factory()->create();

        $user->sendPasswordResetNotification('plain-token');

        Queue::assertPushed(SendQueuedNotifications::class, fn (SendQueuedNotifications $job): bool => $job->notification instanceof ResetPasswordNotification
            && $job->channels === ['mail']
            && $job->tries === 3
            && $job->timeout === 30);
    }

    public function test_the_mail_carries_the_project_copy_and_the_reset_url(): void
    {
        $user = User::factory()->make(['email' => 'ana@example.com']);

        $mail = (new ResetPasswordNotification('plain-token'))->toMail($user);

        $this->assertSame('Reset your Clásicos Autos password', $mail->subject);
        $this->assertSame(__('app.mail.password_reset.greeting'), $mail->greeting);
        $this->assertSame([__('app.mail.password_reset.intro')], $mail->introLines);
        $this->assertSame(__('app.mail.password_reset.action'), $mail->actionText);
        $this->assertSame(route('password.reset', ['token' => 'plain-token', 'email' => 'ana@example.com']), $mail->actionUrl);
        $this->assertSame([
            'This link expires in 60 minutes and works once.',
            __('app.mail.password_reset.outro'),
        ], $mail->outroLines);
        $this->assertSame(__('app.mail.password_reset.salutation'), $mail->salutation);
    }

    public function test_the_expiry_line_follows_the_configured_lifetime(): void
    {
        config(['auth.passwords.users.expire' => 30]);
        $user = User::factory()->make();

        $mail = (new ResetPasswordNotification('plain-token'))->toMail($user);

        $this->assertContains('This link expires in 30 minutes and works once.', $mail->outroLines);
    }

    public function test_the_rendered_mail_never_shows_the_framework_greeting_or_salutation(): void
    {
        $user = User::factory()->make();

        $html = (string) (new ResetPasswordNotification('plain-token'))->toMail($user)->render();

        $this->assertStringContainsString(e(__('app.mail.password_reset.salutation')), $html);
        $this->assertStringNotContainsString('Hello!', $html);
        $this->assertStringNotContainsString('Regards', $html);
    }
}
