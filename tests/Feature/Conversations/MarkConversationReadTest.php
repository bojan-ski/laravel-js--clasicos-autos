<?php

namespace Tests\Feature\Conversations;

use App\Enums\UserStatus;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class MarkConversationReadTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_a_guest_is_sent_to_login_and_a_json_guest_gets_401(): void
    {
        $conversation = Conversation::factory()->create();

        $this->post(route('conversations.read', $conversation))->assertRedirect(route('login'));
        $this->postJson(route('conversations.read', $conversation))->assertUnauthorized();
    }

    public function test_a_participant_marks_only_the_other_partys_unread_messages_in_that_conversation(): void
    {
        $this->travelTo('2026-10-15 12:00:00');
        $conversation = Conversation::factory()->create();
        $fromSeller = $this->message($conversation, $conversation->receiver);
        $fromBuyer = $this->message($conversation, $conversation->sender);
        $alreadyRead = $this->message($conversation, $conversation->receiver, '2026-10-14 09:00:00');
        $elsewhere = Conversation::factory()->for($conversation->sender, 'sender')->create();
        $otherThread = $this->message($elsewhere, $elsewhere->receiver);

        $this->actingAs($conversation->sender)->post(route('conversations.read', $conversation))->assertNoContent();

        $this->assertSame('2026-10-15 12:00:00', $fromSeller->fresh()->read_at->toDateTimeString());
        $this->assertNull($fromBuyer->fresh()->read_at);
        $this->assertSame('2026-10-14 09:00:00', $alreadyRead->fresh()->read_at->toDateTimeString());
        $this->assertNull($otherThread->fresh()->read_at);
    }

    public function test_a_repeat_call_changes_nothing(): void
    {
        $this->travelTo('2026-10-15 12:00:00');
        $conversation = Conversation::factory()->create();
        $fromSeller = $this->message($conversation, $conversation->receiver);
        $this->actingAs($conversation->sender)->post(route('conversations.read', $conversation))->assertNoContent();
        $this->travelTo('2026-10-15 13:00:00');

        $this->post(route('conversations.read', $conversation))->assertNoContent();

        $this->assertSame('2026-10-15 12:00:00', $fromSeller->fresh()->read_at->toDateTimeString());
    }

    public function test_a_suspended_participant_still_marks_read(): void
    {
        $conversation = Conversation::factory()->create();
        $fromSeller = $this->message($conversation, $conversation->receiver);
        $conversation->sender->forceFill(['status' => UserStatus::Suspended])->save();

        $this->actingAs($conversation->sender)->post(route('conversations.read', $conversation))->assertNoContent();

        $this->assertNotNull($fromSeller->fresh()->read_at);
    }

    public function test_an_admin_gets_204_and_writes_nothing(): void
    {
        $conversation = Conversation::factory()->create();
        $this->message($conversation, $conversation->receiver);
        $this->message($conversation, $conversation->sender);

        $this->actingAs(User::factory()->admin()->create())->post(route('conversations.read', $conversation))->assertNoContent();

        $this->assertSame(0, Message::query()->whereNotNull('read_at')->count());
    }

    public function test_a_stranger_gets_404_and_writes_nothing(): void
    {
        $conversation = Conversation::factory()->create();
        $this->message($conversation, $conversation->receiver);

        $this->actingAs(User::factory()->create())->post(route('conversations.read', $conversation))->assertNotFound();

        $this->assertSame(0, Message::query()->whereNotNull('read_at')->count());
    }

    public function test_unknown_or_non_numeric_ids_are_404(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post('/conversations/999999/read')->assertNotFound();
        $this->post('/conversations/abc/read')->assertNotFound();
    }

    private function message(Conversation $conversation, User $sender, ?string $readAt = null): Message
    {
        return Message::factory()->for($conversation)->create(['sender_id' => $sender->id, 'read_at' => $readAt]);
    }
}
