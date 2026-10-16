<?php

namespace Tests\Feature\Consolidation\StageG;

use App\Events\MessageSent;
use App\Models\CarListing;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Services\MessagingService;
use Generator;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ConversationReadStateMatrixTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const string NOW = '2026-10-16 10:00:00';

    private const string AN_HOUR_LATER = '2026-10-16 11:00:00';

    private const string TEXT = 'See you on Saturday at noon.';

    private User $admin;

    private User $buyer;

    private User $seller;

    private CarListing $listing;

    private Conversation $conversation;

    private Conversation $buyersOther;

    private Conversation $sellersOther;

    private Message $sellersRead;

    private Message $buyersUnread;

    private Message $sellersNewest;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->travelTo(self::NOW);
        Event::fake([MessageSent::class]);

        $this->admin = User::factory()->admin()->create();
        $this->buyer = User::factory()->create();
        $this->seller = User::factory()->create();
        $this->listing = CarListing::factory()->approved()->for($this->seller, 'owner')->create();

        $this->conversation = Conversation::factory()->for($this->listing, 'listing')->for($this->buyer, 'sender')->create(['last_message_at' => '2026-10-16 08:00:00']);
        $this->sellersRead = $this->message($this->conversation, $this->seller, '2026-10-16 06:00:00', '2026-10-16 06:30:00');
        $this->buyersUnread = $this->message($this->conversation, $this->buyer, '2026-10-16 07:00:00');
        $this->sellersNewest = $this->message($this->conversation, $this->seller, '2026-10-16 08:00:00');

        $this->buyersOther = Conversation::factory()->for($this->buyer, 'sender')->create(['last_message_at' => '2026-10-16 05:00:00']);
        $this->message($this->buyersOther, $this->buyersOther->receiver, '2026-10-16 05:00:00');

        $this->sellersOther = Conversation::factory()->for($this->listing, 'listing')->create(['last_message_at' => '2026-10-16 04:00:00']);
        $this->message($this->sellersOther, $this->sellersOther->sender, '2026-10-16 04:00:00', '2026-10-16 04:30:00');
    }

    public function test_the_fixture_derives_the_expected_unread_counts(): void
    {
        $this->assertSame(['buyer' => 2, 'seller' => 1, 'admin' => 0], $this->snapshot()['unread']);
    }

    /**
     * @return Generator<string, array{string, string, string}>
     */
    public static function cells(): Generator
    {
        $rows = [
            'open' => ['buyer' => 'M', 'seller' => 'M', 'admin' => 'N'],
            'mark_read' => ['buyer' => 'M', 'seller' => 'M', 'admin' => 'N'],
            'open_again_an_hour_later' => ['buyer' => 'K', 'seller' => 'K', 'admin' => 'N'],
            'send_form' => ['buyer' => 'W', 'seller' => 'W'],
            'send_form_followed' => ['buyer' => 'W+M', 'seller' => 'W+M'],
            'send_json' => ['buyer' => 'W', 'seller' => 'W'],
            'send_json_invalid' => ['buyer' => 'V', 'seller' => 'V'],
            'delete' => ['buyer' => 'X', 'seller' => 'X', 'admin' => 'X'],
        ];

        foreach ($rows as $action => $byActor) {
            foreach ($byActor as $actor => $cell) {
                yield "{$action} by the {$actor}" => [$action, $actor, $cell];
            }
        }
    }

    #[DataProvider('cells')]
    public function test_each_action_by_each_actor(string $action, string $actor, string $cell): void
    {
        $before = $this->snapshot();

        match ($cell) {
            'M' => $this->assertMarkedReadCell($action, $actor, $before),
            'N' => $this->assertNothingWrittenCell($action, $actor, $before),
            'K' => $this->assertAlreadyReadCell($actor, $before),
            'W' => $this->assertWrittenCell($action, $actor, $before),
            'W+M' => $this->assertWrittenThenMarkedCell($actor, $before),
            'V' => $this->assertInvalidCell($actor, $before),
            'X' => $this->assertDeletedCell($actor, $before),
        };
    }

    public function test_opening_a_new_conversation_page_writes_nothing(): void
    {
        $before = $this->snapshot();
        $messages = Message::query()->count();

        $this->actingAs(User::factory()->create())->get(route('conversations.create', $this->listing))->assertOk();

        $this->assertSame($before, $this->snapshot());
        $this->assertSame($messages, Message::query()->count());
        Event::assertNotDispatched(MessageSent::class);
    }

    public function test_the_first_message_creates_the_conversation_with_one_unread_message(): void
    {
        $newBuyer = User::factory()->create();
        $conversations = Conversation::query()->count();

        $response = $this->actingAs($newBuyer)->post(route('conversations.store', $this->listing), ['message' => self::TEXT]);

        $conversation = Conversation::query()->where('sender_id', $newBuyer->id)->sole();
        $response->assertRedirect(route('conversations.show', $conversation));
        $this->assertSame($conversations + 1, Conversation::query()->count());
        $this->assertSame(
            ['sender_id' => $newBuyer->id, 'receiver_id' => $this->seller->id, 'listing_id' => $this->listing->id, 'last_message_at' => self::NOW],
            (array) DB::table('conversations')->where('id', $conversation->id)->first(['sender_id', 'receiver_id', 'listing_id', 'last_message_at']),
        );
        $this->assertSame(
            [['sender_id' => $newBuyer->id, 'message' => self::TEXT, 'read_at' => null, 'created_at' => self::NOW]],
            $this->rows($conversation),
        );
        Event::assertDispatchedTimes(MessageSent::class, 1);
        Event::assertDispatched(MessageSent::class, fn (MessageSent $event): bool => $event->recipient->is($this->seller) && $event->message->conversation_id === $conversation->id);
    }

    public function test_a_second_first_message_by_the_same_buyer_appends_to_the_same_conversation(): void
    {
        $newBuyer = User::factory()->create();
        $conversations = Conversation::query()->count();
        $this->actingAs($newBuyer)->post(route('conversations.store', $this->listing), ['message' => self::TEXT])->assertRedirect();
        $this->travelTo(self::AN_HOUR_LATER);

        $this->actingAs($newBuyer)->post(route('conversations.store', $this->listing), ['message' => 'Is the price negotiable?'])->assertRedirect();

        $conversation = Conversation::query()->where('sender_id', $newBuyer->id)->sole();
        $this->assertSame($conversations + 1, Conversation::query()->count());
        $this->assertSame(self::AN_HOUR_LATER, DB::table('conversations')->where('id', $conversation->id)->value('last_message_at'));
        $this->assertSame(
            [
                ['sender_id' => $newBuyer->id, 'message' => self::TEXT, 'read_at' => null, 'created_at' => self::NOW],
                ['sender_id' => $newBuyer->id, 'message' => 'Is the price negotiable?', 'read_at' => null, 'created_at' => self::AN_HOUR_LATER],
            ],
            $this->rows($conversation),
        );
    }

    /**
     * @param  array<string, mixed>  $before
     */
    private function assertMarkedReadCell(string $action, string $actor, array $before): void
    {
        $this->perform($action, $actor);

        $this->assertSame($this->markedRead($before, $actor), $this->snapshot());
        Event::assertNotDispatched(MessageSent::class);
    }

    /**
     * @param  array<string, mixed>  $before
     */
    private function assertNothingWrittenCell(string $action, string $actor, array $before): void
    {
        $this->perform($action, $actor);

        $this->assertSame($before, $this->snapshot());
        Event::assertNotDispatched(MessageSent::class);
    }

    /**
     * @param  array<string, mixed>  $before
     */
    private function assertAlreadyReadCell(string $actor, array $before): void
    {
        $this->perform('open', $actor);
        $afterFirst = $this->snapshot();
        $this->assertSame($this->markedRead($before, $actor), $afterFirst);
        $this->travelTo(self::AN_HOUR_LATER);

        $this->perform('open', $actor);

        $this->assertSame($afterFirst, $this->snapshot());
    }

    /**
     * @param  array<string, mixed>  $before
     */
    private function assertWrittenCell(string $action, string $actor, array $before): void
    {
        $response = $this->perform($action, $actor);

        $message = $this->newestMessage();
        $this->assertSame($this->written($before, $actor, $message), $this->snapshot());
        $this->assertSentOnceTo($message, $actor);

        if ($action === 'send_json') {
            $response->assertCreated();
            $this->assertSame(['id', 'conversation_id', 'message', 'created_at', 'created_at_human'], array_keys($response->json('data')));
            $this->assertSame($message->id, $response->json('data.id'));

            return;
        }

        $response->assertRedirect(route('conversations.show', $this->conversation));
    }

    /**
     * @param  array<string, mixed>  $before
     */
    private function assertWrittenThenMarkedCell(string $actor, array $before): void
    {
        $this->perform('send_form_followed', $actor)->assertOk();

        $message = $this->newestMessage();
        $this->assertSame($this->markedRead($this->written($before, $actor, $message), $actor), $this->snapshot());
        $this->assertSentOnceTo($message, $actor);
    }

    /**
     * @param  array<string, mixed>  $before
     */
    private function assertInvalidCell(string $actor, array $before): void
    {
        $this->perform('send_json_invalid', $actor)->assertUnprocessable()->assertJsonValidationErrors('message');

        $this->assertSame($before, $this->snapshot());
        Event::assertNotDispatched(MessageSent::class);
    }

    /**
     * @param  array<string, mixed>  $before
     */
    private function assertDeletedCell(string $actor, array $before): void
    {
        $deleted = $actor === 'buyer' ? $this->buyersUnread : $this->sellersNewest;
        $recipient = $actor === 'buyer' ? 'seller' : 'buyer';
        $this->assertModelExists($deleted);

        $this->perform('delete', $actor)
            ->assertRedirect(route('conversations.show', $this->conversation))
            ->assertSessionHas('success', __('app.conversations.deleted'));

        $this->assertModelMissing($deleted);
        $expected = $before;
        unset($expected['read_at'][$deleted->id]);
        $expected['unread'][$recipient]--;
        $this->assertSame($expected, $this->snapshot());
        Event::assertNotDispatched(MessageSent::class);
    }

    private function perform(string $action, string $actor): TestResponse
    {
        $this->actingAs($this->actor($actor));
        $message = ['message' => self::TEXT];

        return match ($action) {
            'open' => $this->get(route('conversations.show', $this->conversation))->assertOk(),
            'mark_read' => $this->post(route('conversations.read', $this->conversation))->assertNoContent(),
            'open_again_an_hour_later' => $this->openTwiceAnHourApart(),
            'send_form' => $this->post(route('conversations.messages.store', $this->conversation), $message),
            'send_form_followed' => $this->followingRedirects()->post(route('conversations.messages.store', $this->conversation), $message),
            'send_json' => $this->postJson(route('conversations.messages.store', $this->conversation), $message),
            'send_json_invalid' => $this->postJson(route('conversations.messages.store', $this->conversation), ['message' => 'Okay']),
            'delete' => $this->delete(route('conversations.messages.destroy', [$this->conversation, $actor === 'buyer' ? $this->buyersUnread : $this->sellersNewest])),
        };
    }

    private function openTwiceAnHourApart(): TestResponse
    {
        $this->get(route('conversations.show', $this->conversation))->assertOk();
        $this->travelTo(self::AN_HOUR_LATER);

        return $this->get(route('conversations.show', $this->conversation))->assertOk();
    }

    private function actor(string $actor): User
    {
        return match ($actor) {
            'buyer' => $this->buyer,
            'seller' => $this->seller,
            'admin' => $this->admin,
        };
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    private function markedRead(array $state, string $actor): array
    {
        $other = $actor === 'buyer' ? $this->seller : $this->buyer;
        $marked = 0;

        foreach (Message::query()->where('conversation_id', $this->conversation->id)->where('sender_id', $other->id)->pluck('id') as $id) {
            if (array_key_exists($id, $state['read_at']) && $state['read_at'][$id] === null) {
                $state['read_at'][$id] = self::NOW;
                $marked++;
            }
        }

        $this->assertGreaterThan(0, $marked);
        $state['unread'][$actor] -= $marked;

        return $state;
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    private function written(array $state, string $actor, Message $message): array
    {
        $this->assertFalse(array_key_exists($message->id, $state['read_at']));
        $this->assertSame(
            ['conversation_id' => $this->conversation->id, 'sender_id' => $this->actor($actor)->id, 'message' => self::TEXT, 'read_at' => null, 'created_at' => self::NOW],
            (array) DB::table('messages')->where('id', $message->id)->first(['conversation_id', 'sender_id', 'message', 'read_at', 'created_at']),
        );

        $state['read_at'][$message->id] = null;
        $state['last_message_at'] = self::NOW;
        $state['unread'][$actor === 'buyer' ? 'seller' : 'buyer']++;

        return $state;
    }

    private function assertSentOnceTo(Message $message, string $actor): void
    {
        $recipient = $actor === 'buyer' ? $this->seller : $this->buyer;

        Event::assertDispatchedTimes(MessageSent::class, 1);
        Event::assertDispatched(MessageSent::class, fn (MessageSent $event): bool => $event->message->is($message) && $event->recipient->is($recipient));
    }

    private function newestMessage(): Message
    {
        return Message::query()->latest('id')->firstOrFail();
    }

    private function message(Conversation $conversation, User $sender, string $createdAt, ?string $readAt = null): Message
    {
        return Message::factory()->for($conversation)->create([
            'sender_id' => $sender->id,
            'read_at' => $readAt,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function rows(Conversation $conversation): array
    {
        return DB::table('messages')
            ->where('conversation_id', $conversation->id)
            ->orderBy('id')
            ->get(['sender_id', 'message', 'read_at', 'created_at'])
            ->map(fn (object $row): array => (array) $row)
            ->all();
    }

    /**
     * @return array{read_at: array<int, ?string>, last_message_at: ?string, unread: array{buyer: int, seller: int, admin: int}, conversations: int}
     */
    private function snapshot(): array
    {
        $messaging = $this->app->make(MessagingService::class);

        return [
            'read_at' => DB::table('messages')
                ->whereIn('conversation_id', [$this->conversation->id, $this->buyersOther->id])
                ->orderBy('id')
                ->pluck('read_at', 'id')
                ->all(),
            'last_message_at' => DB::table('conversations')->where('id', $this->conversation->id)->value('last_message_at'),
            'unread' => [
                'buyer' => $messaging->unreadCountFor($this->buyer),
                'seller' => $messaging->unreadCountFor($this->seller),
                'admin' => $messaging->unreadCountFor($this->admin),
            ],
            'conversations' => Conversation::query()->count(),
        ];
    }
}
