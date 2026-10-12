<?php

namespace App\Enums;

enum UserRole: string
{
    case AppUser = 'app_user';
    case Admin = 'admin';
}
