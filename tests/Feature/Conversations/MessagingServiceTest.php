<?php

namespace Tests\Feature\Conversations;

use App\Models\CarListing;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Services\AccountDeletionService;
use App\Services\ListingService;
use App\Services\MessagingService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class MessagingServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_start_creates_the_conversation_for_the_triple_and_its_first_message(): void
    {
        $this->travelTo('2026-10-15 10:00:00');
        $listing = CarListing::factory()->approved()->create();
        $buyer = User::factory()->create();

        $message = $this->service()->start($listing, $buyer, 'Is it still available?');

        $conversation = Conversation::query()->sole();
        $this->assertSame(
            [$buyer->id, $listing->user_id, $listing->id, '2026-10-15 10:00:00'],
            [$conversation->sender_id, $conversation->receiver_id, $conversation->listing_id, $conversation->last_message_at->toDateTimeString()],
        );
        $this->assertSame(
            [$conversation->id, $buyer->id, 'Is it still available?', null],
            [$message->conversation_id, $message->sender_id, $message->message, $message->read_at],
        );
        $this->assertSame(1, Message::query()->count());
    }

    public function test_a_second_start_for_the_same_triple_appends_to_the_existing_conversation(): void
    {
        $listing = CarListing::factory()->approved()->create();
        $buyer = User::factory()->create();
        $first = $this->service()->start($listing, $buyer, 'First message here.');

        $second = $this->service()->start($listing, $buyer, 'Second first message.');

        $this->assertSame(1, Conversation::query()->count());
        $this->assertSame($first->conversation_id, $second->conversation_id);
        $this->assertSame(2, Message::query()->where('conversation_id', $first->conversation_id)->count());
    }

    public function test_another_buyer_on_the_same_listing_gets_their_own_conversation(): void
    {
        $listing = CarListing::factory()->approved()->create();

        $this->service()->start($listing, User::factory()->create(), 'Hello from buyer one.');
        $this->service()->start($listing, User::factory()->create(), 'Hello from buyer two.');

        $this->assertSame(2, Conversation::query()->where('listing_id', $listing->id)->count());
    }

    public function test_a_failed_first_message_leaves_no_conversation_behind(): void
    {
        $listing = CarListing::factory()->approved()->create();
        $buyer = User::factory()->create();

        try {
            $this->service()->start($listing, $buyer, str_repeat('a', 101));
        } catch (QueryException) {
        }

        $this->assertSame(0, Conversation::query()->count());
        $this->assertSame(0, Message::query()->count());
    }

    public function test_existing_for_finds_only_the_buyers_conversation_on_that_listing(): void
    {
        $conversation = Conversation::factory()->create();
        Conversation::factory()->for($conversation->sender, 'sender')->create();

        $found = $this->service()->existingFor($conversation->listing, $conversation->sender);
        $missing = $this->service()->existingFor($conversation->listing, User::factory()->create());

        $this->assertTrue($found->is($conversation));
        $this->assertNull($missing);
    }

    public function test_send_stores_the_message_and_moves_last_message_at(): void
    {
        $this->travelTo('2026-10-15 10:00:00');
        $conversation = Conversation::factory()->create();
        $this->travelTo('2026-10-15 12:30:00');

        $message = $this->service()->send($conversation, $conversation->receiver, 'Yes, come by Saturday.');

        $this->assertSame([$conversation->receiver_id, 'Yes, come by Saturday.'], [$message->sender_id, $message->message]);
        $this->assertSame('2026-10-15 12:30:00', $conversation->fresh()->last_message_at->toDateTimeString());
    }

    public function test_mark_read_marks_only_the_other_partys_unread_messages(): void
    {
        $this->travelTo('2026-10-15 10:00:00');
        $conversation = Conversation::factory()->create();
        $fromSeller = Message::factory()->for($conversation)->create(['sender_id' => $conversation->receiver_id]);
        $readEarlier = Message::factory()->for($conversation)->create(['sender_id' => $conversation->receiver_id, 'read_at' => '2026-10-14 09:00:00']);
        $fromBuyer = Message::factory()->for($conversation)->create();
        $this->travelTo('2026-10-15 11:00:00');

        $marked = $this->service()->markRead($conversation, $conversation->sender);

        $this->assertSame(1, $marked);
        $this->assertSame('2026-10-15 11:00:00', $fromSeller->fresh()->read_at->toDateTimeString());
        $this->assertSame('2026-10-14 09:00:00', $readEarlier->fresh()->read_at->toDateTimeString());
        $this->assertNull($fromBuyer->fresh()->read_at);
    }

    public function test_a_second_mark_read_changes_nothing(): void
    {
        $conversation = Conversation::factory()->create();
        Message::factory()->for($conversation)->create(['sender_id' => $conversation->receiver_id]);
        $this->service()->markRead($conversation, $conversation->sender);

        $marked = $this->service()->markRead($conversation, $conversation->sender);

        $this->assertSame(0, $marked);
    }

    public function test_mark_read_for_an_admin_writes_nothing_and_runs_no_query(): void
    {
        $conversation = Conversation::factory()->create();
        Message::factory()->for($conversation)->create();
        Message::factory()->for($conversation)->create(['sender_id' => $conversation->receiver_id]);
        $admin = User::factory()->admin()->create();
        DB::enableQueryLog();

        $marked = $this->service()->markRead($conversation, $admin);

        $this->assertSame(0, $marked);
        $this->assertSame([], DB::getQueryLog());
        $this->assertSame(0, Message::query()->whereNotNull('read_at')->count());
    }

    public function test_delete_removes_the_message_and_keeps_last_message_at(): void
    {
        $this->travelTo('2026-10-15 10:00:00');
        $conversation = Conversation::factory()->create();
        $message = Message::factory()->for($conversation)->create();

        $this->service()->delete($message);

        $this->assertModelMissing($message);
        $this->assertSame('2026-10-15 10:00:00', $conversation->fresh()->last_message_at->toDateTimeString());
    }

    public function test_start_and_send_dispatch_no_application_event_and_no_notification(): void
    {
        $listing = CarListing::factory()->approved()->create();
        $buyer = User::factory()->create();
        Notification::fake();
        Event::fake();

        $message = $this->service()->start($listing, $buyer, 'Is it still available?');
        $this->service()->send($message->conversation, $listing->owner, 'Yes, it is available.');

        Notification::assertNothingSent();
        $applicationEvents = array_filter(array_keys(Event::dispatchedEvents()), fn (string $event): bool => Str::startsWith($event, 'App\\'));
        $this->assertSame([], array_values($applicationEvents));
    }

    public function test_deleting_the_listing_removes_its_conversations_and_messages(): void
    {
        Storage::fake('public');
        $message = Message::factory()->create();

        app(ListingService::class)->delete($message->conversation->listing);

        $this->assertModelMissing($message->conversation);
        $this->assertModelMissing($message);
    }

    public function test_deleting_the_buyers_account_removes_the_conversation_and_messages(): void
    {
        Storage::fake('public');
        $message = Message::factory()->create();

        app(AccountDeletionService::class)->delete($message->conversation->sender);

        $this->assertModelMissing($message->conversation);
        $this->assertModelMissing($message);
    }

    public function test_deleting_the_sellers_account_removes_the_conversation_and_messages(): void
    {
        Storage::fake('public');
        $message = Message::factory()->create();

        app(AccountDeletionService::class)->delete($message->conversation->receiver);

        $this->assertModelMissing($message->conversation);
        $this->assertModelMissing($message);
    }

    private function service(): MessagingService
    {
        return app(MessagingService::class);
    }
}
