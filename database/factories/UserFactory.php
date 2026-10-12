<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'username' => Str::limit(fake()->unique()->userName(), 64, ''),
            'email' => Str::lower(fake()->unique()->safeEmail()),
            'phone_number' => '+'.fake()->numerify('##########'),
            'password' => static::$password ??= Hash::make('password'),
            'role' => UserRole::AppUser,
            'status' => UserStatus::Active,
            'remember_token' => Str::random(10),
        ];
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::Admin,
        ]);
    }

    public function suspended(?CarbonInterface $until = null): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => UserStatus::Suspended,
            'suspended_at' => now(),
            'suspended_until' => $until,
            'suspension_reason' => 'Repeated abusive messages.',
        ]);
    }
}
