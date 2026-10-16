<?php

namespace Database\Factories;

use App\Enums\ContactMessageStatus;
use App\Models\ContactMessage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContactMessage>
 */
class ContactMessageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'subject' => fake()->text(120),
            'message' => fake()->text(1000),
            'status' => ContactMessageStatus::New,
        ];
    }

    public function handled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ContactMessageStatus::Handled,
            'handled_at' => now(),
        ]);
    }
}
