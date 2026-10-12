<?php

namespace App\Services;

use App\Models\User;

class PasswordService
{
    public function update(User $user, string $password): void
    {
        $user->password = $password;
        $user->save();
    }
}
