<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Auth\Passwords\PasswordBroker;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Support\Timebox;

class PasswordResetService
{
    public const int LINK_CHECK_MICROSECONDS = 400_000;

    public function __construct(
        private PasswordBroker $broker,
        private PasswordService $passwords,
        private Timebox $timebox,
    ) {}

    public function sendLink(string $email): void
    {
        $this->broker->sendResetLink(['email' => $email]);
    }

    public function linkIsValid(string $token, string $email): bool
    {
        if ($email === '') {
            return false;
        }

        return $this->timebox->call(function () use ($token, $email): bool {
            $user = $this->broker->getUser(['email' => $email]);

            return $user !== null && $this->broker->tokenExists($user, $token);
        }, self::LINK_CHECK_MICROSECONDS);
    }

    public function reset(string $token, string $email, string $password): bool
    {
        $status = $this->broker->reset(
            ['token' => $token, 'email' => $email, 'password' => $password],
            function (User $user, string $password): void {
                $user->setRememberToken(Str::random(60));

                $this->passwords->update($user, $password);
            },
        );

        return $status === Password::PASSWORD_RESET;
    }
}
