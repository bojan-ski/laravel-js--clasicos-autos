<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Auth\Passwords\PasswordBroker;
use LogicException;

class AccountDeletionService
{
    public function __construct(
        private PasswordBroker $broker,
    ) {}

    public function delete(User $user): void
    {
        if ($user->isAdmin()) {
            throw new LogicException('Admin accounts cannot be deleted.');
        }

        $this->broker->deleteToken($user);
        $user->delete();
    }
}
