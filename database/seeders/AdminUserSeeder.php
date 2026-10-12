<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $admin = config('auth.admin');
        $email = mb_strtolower(trim((string) $admin['email']));
        $password = (string) $admin['password'];

        if ($email === '') {
            throw new RuntimeException('ADMIN_EMAIL is not set; refusing to seed the admin.');
        }

        if (mb_strlen($password) < 6) {
            throw new RuntimeException('ADMIN_PASSWORD is missing or shorter than 6 characters; refusing to seed the admin.');
        }

        $existing = User::query()->where('email', $email)->first();

        if ($existing !== null) {
            if (! $existing->isAdmin()) {
                $this->command?->warn("A non-admin user already uses {$email}; it was left unchanged.");
            }

            return;
        }

        $user = new User([
            'username' => $admin['username'],
            'email' => $email,
            'phone_number' => $admin['phone_number'],
            'password' => $password,
        ]);
        $user->role = UserRole::Admin;
        $user->status = UserStatus::Active;
        $user->save();
    }
}
