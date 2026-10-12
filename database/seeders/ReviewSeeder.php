<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Seeder;

class ReviewSeeder extends Seeder
{
    public function run(): void
    {
        if (User::query()->where('email', 'review@example.com')->exists()) {
            return;
        }

        $user = new User([
            'username' => 'review user',
            'email' => 'review@example.com',
            'phone_number' => '+5355512345',
            'password' => 'password',
        ]);
        $user->role = UserRole::AppUser;
        $user->status = UserStatus::Active;
        $user->save();
    }
}
