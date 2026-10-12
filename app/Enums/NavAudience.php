<?php

namespace App\Enums;

use App\Models\User;

enum NavAudience: string
{
    case Guest = 'guest';
    case Member = 'member';
    case Admin = 'admin';

    public static function forUser(?User $user): self
    {
        if ($user === null) {
            return self::Guest;
        }

        return $user->isAdmin() ? self::Admin : self::Member;
    }
}
