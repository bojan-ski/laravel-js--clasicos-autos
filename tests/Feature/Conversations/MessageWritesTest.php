<?php

namespace Tests\Feature\Conversations;

use App\Enums\ListingStatus;
use App\Enums\UserStatus;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MessageWritesTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_a_guest_is_sent_to_login_from_both_writes(): void
    {
        $message = Message::factory()->create();

        $this->post(route('conversations.messages.store', $message->conversation), ['message' => 'Hello there!'])->assertRedirect(route('login'));
        $this->delete(route('conversations.messages.destroy', [$message->conversation, $message]))->assertRedirect(route('login'));
        $this->assertModelExists($message);
        $this->assertSame(1, Message::query()->count());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function participants(): array
    {
        return [
            'buyer' => ['sender'],
            'seller' => ['receiver'],
        ];
    }

    #[DataProvider('participants')]
    public function test_a_participant_reply_is_stored_and_lands_on_its_anchor_without_a_flash(string $role): void
    {
        $this->travelTo('2026-10-15 10:00:00');
        $conversation = Conversation::factory()->create();
        $this->travelTo('2026-10-15 11:00:00');
        $author = $conversation->{$role};

        $response = $this->actingAs($author)->post(route('conversations.messages.store', $conversation), ['message' => 'See you on Saturday.']);

        $message = Message::query()->sole();
        $response->assertRedirect(route('conversations.show', $conversation).'#message-'.$message->id)
            ->assertSessionMissing('success');
        $this->assertSame([$author->id, 'See you on Saturday.'], [$message->sender_id, $message->message]);
        $this->assertSame('2026-10-15 11:00:00', $conversation->fresh()->last_message_at->toDateTimeString());
    }

    public function test_server_controlled_fields_in_a_reply_are_ignored(): void
    {
        $conversation = Conversation::factory()->create();
        $other = Conversation::factory()->create();

        $this->actingAs($conversation->sender)->post(route('conversations.messages.store', $conversation), [
            'message' => 'See you on Saturday.',
            'sender_id' => $conversation->receiver_id,
            'conversation_id' => $other->id,
            'read_at' => '2026-01-01 00:00:00',
        ]);

        $message = Message::query()->sole();
        $this->assertSame([$conversation->id, $conversation->sender_id, null], [$message->conversation_id, $message->sender_id, $message->read_at]);
    }

    public function test_an_invalid_reply_returns_to_the_thread_with_the_error_and_old_input(): void
    {
        $conversation = Conversation::factory()->create();

        $this->actingAs($conversation->sender)
            ->from('https://evil.example/')
            ->post(route('conversations.messages.store', $conversation), ['message' => 'abcd'])
            ->assertRedirect(route('conversations.show', $conversation))
            ->assertSessionHasErrors(['message' => 'The message field must be at least 5 characters.'])
            ->assertSessionHasInput('message', 'abcd');

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
    public function test_a_refused_reply_answers_before_validation_and_stores_nothing(string $viewerKind, int $status): void
    {
        $conversation = Conversation::factory()->create();
        $viewer = match ($viewerKind) {
            'admin' => User::factory()->admin()->create(),
            'stranger' => User::factory()->create(),
        };

        $this->actingAs($viewer)
            ->post(route('conversations.messages.store', $conversation), [])
            ->assertStatus($status)
            ->assertSessionDoesntHaveErrors();

        $this->assertSame(0, Message::query()->count());
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function hiddenListingsPerParticipant(): array
    {
        $cases = [];

        foreach (['pending', 'rejected'] as $state) {
            foreach (['sender' => 'buyer', 'receiver' => 'seller'] as $role => $label) {
                $cases["{$state}, {$label}"] = [$state, $role];
            }
        }

        return $cases + ['suspended owner, buyer' => ['suspended owner', 'sender']];
    }

    #[DataProvider('hiddenListingsPerParticipant')]
    public function test_participants_keep_reading_and_replying_after_the_listing_is_hidden(string $state, string $role): void
    {
        $conversation = Conversation::factory()->create();
        $this->hide($conversation, $state);

        $this->actingAs($conversation->{$role})->get(route('conversations.show', $conversation))->assertOk();
        $this->post(route('conversations.messages.store', $conversation), ['message' => 'Still interested?'])->assertRedirect();

        $this->assertSame(1, Message::query()->count());
    }

    public function test_a_suspended_owner_in_a_live_session_is_signed_out_of_the_thread_and_the_reply(): void
    {
        $conversation = Conversation::factory()->create();
        $this->hide($conversation, 'suspended owner');
        $owner = $conversation->receiver->fresh();

        $this->actingAs($owner)->get(route('conversations.show', $conversation))->assertRedirect(route('login'));
        $this->actingAs($owner)->post(route('conversations.messages.store', $conversation), ['message' => 'Still interested?'])->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertSame(0, Message::query()->count());
    }

    public function test_the_sender_deletes_their_message_and_last_message_at_stays(): void
    {
        $this->travelTo('2026-10-15 10:00:00');
        $message = Message::factory()->create();
        $conversation = $message->conversation;

        $response = $this->actingAs($conversation->sender)->delete(route('conversations.messages.destroy', [$conversation, $message]));

        $response->assertRedirect(route('conversations.show', $conversation))
            ->assertSessionHas('success', 'Message deleted.');
        $this->assertModelMissing($message);
        $this->assertSame('2026-10-15 10:00:00', $conversation->fresh()->last_message_at->toDateTimeString());
    }

    public function test_an_admin_deletes_any_message(): void
    {
        $message = Message::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->delete(route('conversations.messages.destroy', [$message->conversation, $message]))
            ->assertSessionHas('success', 'Message deleted.');

        $this->assertModelMissing($message);
    }

    public function test_a_suspended_sender_in_a_live_session_is_signed_out_and_deletes_nothing(): void
    {
        $message = Message::factory()->create();

        $this->actingAs($this->suspend($message->conversation->sender))
            ->delete(route('conversations.messages.destroy', [$message->conversation, $message]))
            ->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertModelExists($message);
    }

    /**
     * @return array<string, array{string, int}>
     */
    public static function refusedDeleters(): array
    {
        return [
            'the other participant' => ['receiver', 403],
            'a stranger' => ['stranger', 404],
        ];
    }

    #[DataProvider('refusedDeleters')]
    public function test_a_refused_delete_keeps_the_message(string $viewerKind, int $status): void
    {
        $message = Message::factory()->create();
        $viewer = $viewerKind === 'stranger' ? User::factory()->create() : $message->conversation->receiver;

        $this->actingAs($viewer)
            ->delete(route('conversations.messages.destroy', [$message->conversation, $message]))
            ->assertStatus($status);

        $this->assertModelExists($message);
    }

    public function test_a_message_from_another_conversation_is_404(): void
    {
        $conversation = Conversation::factory()->create();
        $foreign = Message::factory()->create(['sender_id' => $conversation->sender_id]);

        $this->actingAs($conversation->sender)
            ->delete(route('conversations.messages.destroy', [$conversation, $foreign]))
            ->assertNotFound();

        $this->assertModelExists($foreign);
    }

    public function test_unknown_or_non_numeric_ids_are_404(): void
    {
        $conversation = Conversation::factory()->create();
        $this->actingAs($conversation->sender);

        $this->post('/conversations/999999/messages', ['message' => 'Hello there!'])->assertNotFound();
        $this->delete("/conversations/{$conversation->id}/messages/999999")->assertNotFound();
        $this->delete("/conversations/{$conversation->id}/messages/abc")->assertNotFound();
    }

    private function hide(Conversation $conversation, string $state): void
    {
        match ($state) {
            'pending' => $conversation->listing->forceFill(['status' => ListingStatus::Pending])->save(),
            'rejected' => $conversation->listing->forceFill(['status' => ListingStatus::Rejected, 'rejection_reason' => 'Blurry photos.'])->save(),
            'suspended owner' => $this->suspend($conversation->receiver),
        };
    }

    private function suspend(User $user): User
    {
        $user->forceFill(['status' => UserStatus::Suspended])->save();

        return $user;
    }
}
