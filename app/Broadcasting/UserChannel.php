<?php

namespace App\Broadcasting;

use App\Models\User;

class UserChannel
{
    public function join(User $user, string $id): bool
    {
        return (string) $user->getKey() === $id && $user->isActiveAppUser();
    }
}
