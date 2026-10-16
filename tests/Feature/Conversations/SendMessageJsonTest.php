<?php

namespace Tests\Feature\Conversations;

use App\Enums\UserStatus;
use App\Events\MessageSent;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SendMessageJsonTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @return array<string, array{string, string}>
     */
    public static function participants(): array
    {
        return [
            'buyer' => ['sender', 'receiver'],
            'seller' => ['receiver', 'sender'],
        ];
    }

    #[DataProvider('participants')]
    public function test_a_json_reply_answers_201_with_the_message_resource(string $author, string $recipient): void
    {
        Event::fake([MessageSent::class]);
        $this->travelTo('2026-10-15 10:00:00');
        $conversation = Conversation::factory()->create();
        $this->travelTo('2026-10-15 11:00:00');

        $response = $this->actingAs($conversation->{$author})
            ->postJson(route('conversations.messages.store', $conversation), ['message' => 'See you on Saturday.']);

        $message = Message::query()->sole();
        $response->assertCreated()->assertExactJson(['data' => [
            'id' => $message->id,
            'conversation_id' => $conversation->id,
            'message' => 'See you on Saturday.',
            'created_at' => '2026-10-15T11:00:00+00:00',
            'created_at_human' => '0 seconds ago',
        ]]);
        $this->assertSame($conversation->{$author}->id, $message->sender_id);
        $this->assertSame('2026-10-15 11:00:00', $conversation->fresh()->last_message_at->toDateTimeString());
        Event::assertDispatchedTimes(MessageSent::class, 1);
        Event::assertDispatched(MessageSent::class, fn (MessageSent $event): bool => $event->message->is($message) && $event->recipient->is($conversation->{$recipient}));
    }

    public function test_server_controlled_fields_in_a_json_reply_are_ignored(): void
    {
        $conversation = Conversation::factory()->create();
        $other = Conversation::factory()->create();

        $this->actingAs($conversation->sender)->postJson(route('conversations.messages.store', $conversation), [
            'message' => 'See you on Saturday.',
            'sender_id' => $conversation->receiver_id,
            'conversation_id' => $other->id,
            'read_at' => '2026-01-01 00:00:00',
        ])->assertCreated();

        $message = Message::query()->sole();
        $this->assertSame([$conversation->id, $conversation->sender_id, null], [$message->conversation_id, $message->sender_id, $message->read_at]);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function invalidMessages(): array
    {
        return [
            'too short' => ['abcd', 'The message field must be at least 5 characters.'],
            'too long' => [str_repeat('a', 101), 'The message field must not be greater than 100 characters.'],
            'whitespace only' => ['         ', 'The message field is required.'],
        ];
    }

    #[DataProvider('invalidMessages')]
    public function test_an_invalid_json_reply_answers_422_and_stores_nothing(string $text, string $error): void
    {
        Event::fake([MessageSent::class]);
        $conversation = Conversation::factory()->create();

        $this->actingAs($conversation->sender)
            ->postJson(route('conversations.messages.store', $conversation), ['message' => $text])
            ->assertUnprocessable()
            ->assertExactJson(['message' => $error, 'errors' => ['message' => [$error]]]);

        $this->assertSame(0, Message::query()->count());
        Event::assertNotDispatched(MessageSent::class);
    }

    public function test_a_guest_json_reply_answers_401(): void
    {
        $conversation = Conversation::factory()->create();

        $this->postJson(route('conversations.messages.store', $conversation), [])->assertUnauthorized();

        $this->assertSame(0, Message::query()->count());
    }

    public function test_a_suspended_participant_json_reply_answers_401_and_signs_out(): void
    {
        $conversation = Conversation::factory()->create();
        $conversation->sender->forceFill(['status' => UserStatus::Suspended])->save();

        $this->actingAs($conversation->sender)
            ->postJson(route('conversations.messages.store', $conversation), [])
            ->assertUnauthorized();

        $this->assertGuest();
        $this->assertSame(0, Message::query()->count());
    }

    /**
     * @return array<string, array{string, int}>
     */
    public static function refusedRepliers(): array
    {
        return [
            'admin' => ['admin', 403],
            'stranger' => ['stranger', 404],
        ];
    }

    #[DataProvider('refusedRepliers')]
    public function test_a_refused_json_reply_answers_before_validation(string $viewerKind, int $status): void
    {
        $conversation = Conversation::factory()->create();
        $viewer = match ($viewerKind) {
            'admin' => User::factory()->admin()->create(),
            'stranger' => User::factory()->create(),
        };

        $this->actingAs($viewer)
            ->postJson(route('conversations.messages.store', $conversation), [])
            ->assertStatus($status)
            ->assertJsonMissingPath('errors');

        $this->assertSame(0, Message::query()->count());
    }

    public function test_an_unknown_conversation_json_reply_answers_404(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson('/conversations/999999/messages', ['message' => 'Hello there!'])
            ->assertNotFound();
    }
}
