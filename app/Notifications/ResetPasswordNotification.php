<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;

#[Tries(3)]
#[Timeout(30)]
class ResetPasswordNotification extends ResetPassword implements ShouldQueue
{
    use Queueable;

    protected function buildMailMessage(mixed $url): MailMessage
    {
        return (new MailMessage)
            ->subject(__('app.mail.password_reset.subject'))
            ->greeting(__('app.mail.password_reset.greeting'))
            ->line(__('app.mail.password_reset.intro'))
            ->action(__('app.mail.password_reset.action'), $url)
            ->line(__('app.mail.password_reset.expires', ['count' => config('auth.passwords.users.expire')]))
            ->line(__('app.mail.password_reset.outro'))
            ->salutation(__('app.mail.password_reset.salutation'));
    }
}
