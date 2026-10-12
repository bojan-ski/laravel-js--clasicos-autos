<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Notifications\ResetPasswordNotification;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['username', 'email', 'phone_number', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * @var array<string, string>
     */
    protected $attributes = [
        'role' => 'app_user',
        'status' => 'active',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => UserRole::class,
            'status' => UserStatus::class,
            'suspended_at' => 'datetime',
            'suspended_until' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function isAppUser(): bool
    {
        return $this->role === UserRole::AppUser;
    }

    public function isSuspended(): bool
    {
        return $this->status === UserStatus::Suspended;
    }

    public function initials(): string
    {
        $parts = preg_split('/[\s\-_.]+/u', trim($this->username), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $initials = count($parts) >= 2
            ? mb_substr($parts[0], 0, 1).mb_substr($parts[1], 0, 1)
            : mb_substr($parts[0] ?? '', 0, 2);

        return mb_strtoupper($initials);
    }

    public function landingRoute(): string
    {
        return $this->isAdmin() ? 'admin.account' : 'profile';
    }

    /**
     * @param  string  $token
     */
    public function sendPasswordResetNotification(#[\SensitiveParameter] mixed $token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }
}
