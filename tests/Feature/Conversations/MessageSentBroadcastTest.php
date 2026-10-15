<?php

namespace Tests\Feature\Conversations;

use App\Enums\UserStatus;
use App\Events\MessageSent;
use App\Models\CarListing;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Services\MessagingService;
use Illuminate\Broadcasting\BroadcastEvent;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class MessageSentBroadcastTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_the_first_message_is_broadcast_once_to_the_listings_owner(): void
    {
        Event::fake([MessageSent::class]);
        $listing = CarListing::factory()->approved()->create();
        $buyer = User::factory()->create();

        $this->actingAs($buyer)->post(route('conversations.store', $listing), ['message' => 'Is it still available?']);

        $message = Message::query()->sole();
        Event::assertDispatchedTimes(MessageSent::class, 1);
        Event::assertDispatched(MessageSent::class, fn (MessageSent $event): bool => $event->message->is($message) && $event->recipient->is($listing->owner));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function replyDirections(): array
    {
        return [
            'buyer to seller' => ['sender', 'receiver'],
            'seller to buyer' => ['receiver', 'sender'],
        ];
    }

    #[DataProvider('replyDirections')]
    public function test_a_reply_is_broadcast_once_to_the_other_participant(string $author, string $recipient): void
    {
        Event::fake([MessageSent::class]);
        $conversation = Conversation::factory()->create();

        $this->actingAs($conversation->{$author})->post(route('conversations.messages.store', $conversation), ['message' => 'See you on Saturday.']);

        $message = Message::query()->sole();
        Event::assertDispatchedTimes(MessageSent::class, 1);
        Event::assertDispatched(MessageSent::class, fn (MessageSent $event): bool => $event->message->is($message) && $event->recipient->is($conversation->{$recipient}));
    }

    public function test_deleting_marking_read_and_reading_pages_broadcast_nothing(): void
    {
        $conversation = Conversation::factory()->create();
        $message = Message::factory()->for($conversation)->create(['sender_id' => $conversation->sender_id]);
        Event::fake([MessageSent::class]);

        $this->actingAs($conversation->receiver)->get(route('conversations.show', $conversation))->assertOk();
        $this->get(route('conversations.index'))->assertOk();
        $this->post(route('conversations.read', $conversation))->assertNoContent();
        $this->actingAs($conversation->sender)->delete(route('conversations.messages.destroy', [$conversation, $message]));

        $this->assertModelMissing($message);
        Event::assertNotDispatched(MessageSent::class);
    }

    public function test_an_invalid_first_message_or_reply_broadcasts_nothing(): void
    {
        Event::fake([MessageSent::class]);
        $conversation = Conversation::factory()->create();

        $this->actingAs(User::factory()->create())->post(route('conversations.store', CarListing::factory()->approved()->create()), ['message' => 'Hi'])->assertSessionHasErrors('message');
        $this->actingAs($conversation->sender)->post(route('conversations.messages.store', $conversation), ['message' => 'Hi'])->assertSessionHasErrors('message');

        $this->assertSame(0, Message::query()->count());
        Event::assertNotDispatched(MessageSent::class);
    }

    /**
     * @return array<string, array{string, int}>
     */
    public static function refusedWriters(): array
    {
        return [
            'stranger' => ['stranger', 404],
            'admin' => ['admin', 403],
            'suspended participant (signed out)' => ['suspended participant', 302],
        ];
    }

    #[DataProvider('refusedWriters')]
    public function test_a_refused_reply_broadcasts_nothing(string $writerKind, int $status): void
    {
        Event::fake([MessageSent::class]);
        $conversation = Conversation::factory()->create();
        $writer = match ($writerKind) {
            'stranger' => User::factory()->create(),
            'admin' => User::factory()->admin()->create(),
            'suspended participant' => $this->suspend($conversation->sender),
        };

        $this->actingAs($writer)->post(route('conversations.messages.store', $conversation), ['message' => 'See you on Saturday.'])->assertStatus($status);

        Event::assertNotDispatched(MessageSent::class);
    }

    public function test_the_owner_starting_a_conversation_on_their_own_listing_broadcasts_nothing(): void
    {
        Event::fake([MessageSent::class]);
        $listing = CarListing::factory()->approved()->create();

        $this->actingAs($listing->owner)->post(route('conversations.store', $listing), ['message' => 'Is it still available?'])->assertForbidden();

        Event::assertNotDispatched(MessageSent::class);
    }

    public function test_start_and_send_broadcast_nothing_when_the_callers_transaction_rolls_back(): void
    {
        Event::fake([MessageSent::class]);
        $listing = CarListing::factory()->approved()->create();
        $buyer = User::factory()->create();
        $caught = null;

        try {
            DB::transaction(function () use ($listing, $buyer): void {
                $message = $this->service()->start($listing, $buyer, 'Is it still available?');
                $this->service()->send($message->conversation, $listing->owner, 'Yes, it is available.');

                throw new RuntimeException('Caller failed after the writes.');
            });
        } catch (RuntimeException $exception) {
            $caught = $exception;
        }

        $this->assertSame('Caller failed after the writes.', $caught?->getMessage());
        $this->assertSame(0, Message::query()->count());
        Event::assertNotDispatched(MessageSent::class);
    }

    public function test_start_and_send_broadcast_after_the_callers_transaction_commits(): void
    {
        Event::fake([MessageSent::class]);
        $listing = CarListing::factory()->approved()->create();
        $buyer = User::factory()->create();

        DB::transaction(function () use ($listing, $buyer): void {
            $message = $this->service()->start($listing, $buyer, 'Is it still available?');
            $this->service()->send($message->conversation, $listing->owner, 'Yes, it is available.');

            Event::assertNotDispatched(MessageSent::class);
        });

        Event::assertDispatchedTimes(MessageSent::class, 2);
    }

    public function test_each_stored_message_queues_one_broadcast_job_with_its_retry_settings(): void
    {
        Queue::fake();
        $listing = CarListing::factory()->approved()->create();

        $message = $this->service()->start($listing, User::factory()->create(), 'Is it still available?');
        $this->service()->send($message->conversation, $listing->owner, 'Yes, it is available.');

        Queue::assertPushed(BroadcastEvent::class, 2);
        Queue::assertPushed(BroadcastEvent::class, fn (BroadcastEvent $job): bool => $job->event instanceof MessageSent
            && [$job->tries, $job->timeout, $job->backoff] === [3, 15, 5]);
    }

    public function test_the_event_names_the_recipients_private_channel_and_the_message_sent_event(): void
    {
        $message = Message::factory()->create();
        $recipient = $message->conversation->receiver;

        $event = new MessageSent($message, $recipient);

        $this->assertSame(['private-user.'.$recipient->id], array_column($event->broadcastOn(), 'name'));
        $this->assertSame('message.sent', $event->broadcastAs());
    }

    public function test_no_broadcast_job_is_queued_for_a_suspended_recipient(): void
    {
        Queue::fake();
        $conversation = Conversation::factory()->create();
        $this->suspend($conversation->receiver);

        $this->service()->send($conversation, $conversation->sender, 'See you on Saturday.');

        $this->assertSame(1, Message::query()->count());
        Queue::assertNotPushed(BroadcastEvent::class);
    }

    public function test_a_queued_event_whose_recipient_was_suspended_meanwhile_names_no_channel(): void
    {
        $message = Message::factory()->create();
        $recipient = $message->conversation->receiver;
        $serialized = serialize(new MessageSent($message, $recipient));

        $this->suspend($recipient);
        $restored = unserialize($serialized);

        $this->assertTrue($restored->recipient->isSuspended());
        $this->assertSame([], $restored->broadcastOn());
    }

    public function test_the_payload_holds_exactly_the_message_fields_and_the_recipients_unread_count(): void
    {
        $this->travelTo('2026-10-15 10:00:00');
        $conversation = Conversation::factory()->create();
        $buyer = $conversation->sender;
        $seller = $conversation->receiver;
        $elsewhere = Conversation::factory()->for($seller, 'sender')->create();
        Message::factory()->for($elsewhere)->create(['sender_id' => $elsewhere->receiver_id]);
        Message::factory()->for($conversation)->create(['sender_id' => $seller->id]);
        Message::factory()->for($conversation)->create(['sender_id' => $buyer->id, 'read_at' => now()]);
        $message = $this->service()->send($conversation, $buyer, 'Is the <b>engine</b> original?');
        $this->travelTo('2026-10-15 12:00:00');

        $payload = (new MessageSent($message, $seller))->broadcastWith();

        $this->assertSame([
            'id' => $message->id,
            'conversation_id' => $conversation->id,
            'message' => 'Is the <b>engine</b> original?',
            'created_at' => '2026-10-15T10:00:00+00:00',
            'created_at_human' => '2 hours ago',
            'unread_count' => 2,
        ], $payload);
    }

    private function service(): MessagingService
    {
        return $this->app->make(MessagingService::class);
    }

    private function suspend(User $user): User
    {
        $user->forceFill(['status' => UserStatus::Suspended])->save();

        return $user;
    }
}
