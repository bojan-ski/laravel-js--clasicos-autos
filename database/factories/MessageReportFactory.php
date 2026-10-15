<?php

namespace Database\Factories;

use App\Enums\ReportOutcome;
use App\Enums\ReportReason;
use App\Enums\ReportStatus;
use App\Models\Message;
use App\Models\MessageReport;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MessageReport>
 */
class MessageReportFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'message_id' => Message::factory(),
            'conversation_id' => fn (array $attributes) => $this->message($attributes)->conversation_id,
            'message_text' => fn (array $attributes) => $this->message($attributes)->message,
            'reported_user_id' => fn (array $attributes) => $this->message($attributes)->sender_id,
            'reporter_id' => function (array $attributes): int {
                $conversation = $this->message($attributes)->conversation;

                return $conversation->sender_id === $attributes['reported_user_id']
                    ? $conversation->receiver_id
                    : $conversation->sender_id;
            },
            'reason' => fake()->randomElement(ReportReason::cases()),
            'note' => null,
            'status' => ReportStatus::Open,
        ];
    }

    public function resolved(ReportOutcome $outcome = ReportOutcome::Dismissed): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ReportStatus::Resolved,
            'outcome' => $outcome,
            'resolved_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function message(array $attributes): Message
    {
        return Message::query()->with('conversation')->findOrFail($attributes['message_id']);
    }
}
