<?php

namespace App\Services;

use App\Models\User;
use LogicException;

class AccountDeletionService
{
    public function delete(User $user): void
    {
        if ($user->isAdmin()) {
            throw new LogicException('Admin accounts cannot be deleted.');
        }

        $user->delete();
    }
}
