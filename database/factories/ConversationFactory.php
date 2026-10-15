<?php

namespace Database\Factories;

use App\Models\CarListing;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Conversation>
 */
class ConversationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'listing_id' => CarListing::factory()->approved(),
            'sender_id' => User::factory(),
            'receiver_id' => fn (array $attributes) => CarListing::query()->findOrFail($attributes['listing_id'])->user_id,
            'last_message_at' => now(),
        ];
    }
}
