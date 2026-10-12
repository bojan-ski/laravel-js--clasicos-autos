<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use App\Support\PhoneNumber;

class RegistrationService
{
    /**
     * @param  array{username: string, email: string, phone_number: string, password: string}  $data
     */
    public function register(array $data): User
    {
        $user = new User([
            'username' => $data['username'],
            'email' => $data['email'],
            'phone_number' => PhoneNumber::normalize($data['phone_number']),
            'password' => $data['password'],
        ]);
        $user->role = UserRole::AppUser;
        $user->status = UserStatus::Active;
        $user->save();

        return $user;
    }
}
