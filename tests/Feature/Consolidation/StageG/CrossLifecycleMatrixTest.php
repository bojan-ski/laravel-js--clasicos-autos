<?php

namespace Tests\Feature\Consolidation\StageG;

use App\Events\MessageSent;
use App\Models\CarListing;
use App\Models\CarModel;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\MessageReport;
use App\Models\User;
use App\Services\MessagingService;
use App\Support\ListingFormValues;
use Carbon\CarbonInterface;
use Illuminate\Broadcasting\BroadcastEvent;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CrossLifecycleMatrixTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const string NOW = '2026-10-16 10:00:00';

    private const string REASON = 'Asked several buyers for deposits outside the app.';

    private const string TEXT = 'See you on Saturday at noon.';

    private User $admin;

    private User $buyer;

    private User $seller;

    private CarListing $listing;

    private Conversation $conversation;

    private Conversation $buyersOther;

    private Conversation $sellersOther;

    private MessageReport $report;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Storage::fake('public');
        $this->travelTo(self::NOW);
        Queue::fake();

        $this->admin = User::factory()->admin()->create();
    }

    public function test_suspending_the_seller_hides_the_listing_but_keeps_the_thread_writable_without_a_push(): void
    {
        $this->fixture();
        $buyersUnread = $this->unreadFor($this->buyer);

        $this->suspend($this->seller);

        $this->assertListingStatusForGuest(404);
        $this->assertSame($buyersUnread, $this->unreadFor($this->buyer));
        $this->assertThreadShowsMessagesOf($this->buyer, $this->seller);
        $this->assertSendIsStored($this->buyer);
        Queue::assertNotPushed(BroadcastEvent::class);

        $conversations = Conversation::query()->count();
        $this->actingAs(User::factory()->create())
            ->post(route('conversations.store', $this->listing), ['message' => self::TEXT])
            ->assertNotFound();
        $this->assertSame($conversations, Conversation::query()->count());
    }

    public function test_suspending_the_buyer_keeps_the_listing_public_and_the_thread_writable_without_a_push(): void
    {
        $this->fixture();

        $this->suspend($this->buyer);

        $this->assertListingStatusForGuest(200);
        $this->assertThreadShowsMessagesOf($this->seller, $this->buyer);
        $this->assertSendIsStored($this->seller);
        Queue::assertNotPushed(BroadcastEvent::class);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function restorations(): array
    {
        return [
            'unsuspended by an admin from a timed suspension' => ['unsuspend'],
            'lifted by the command from an expired suspension' => ['lift'],
        ];
    }

    #[DataProvider('restorations')]
    public function test_restoring_the_seller_makes_the_listing_public_and_pushes_the_next_message_to_them(string $restoration): void
    {
        $this->fixture(sellerSuspendedUntil: $restoration === 'unsuspend' ? now()->addDays(5) : now()->subHour());
        $this->assertListingStatusForGuest(404);

        if ($restoration === 'unsuspend') {
            $this->actingAs($this->admin)->patch(route('admin.users.unsuspend', $this->seller))->assertRedirect(route('admin.users'));
        } else {
            $this->artisan('suspensions:lift')->assertSuccessful();
        }

        $this->assertListingStatusForGuest(200);
        $this->assertSendIsStored($this->buyer);
        Queue::assertPushed(BroadcastEvent::class, 1);
        Queue::assertPushed(BroadcastEvent::class, fn (BroadcastEvent $job): bool => $job->event instanceof MessageSent && $job->event->recipient->is($this->seller));
    }

    public function test_returning_the_listing_to_pending_hides_it_but_keeps_the_thread_and_bookmarks(): void
    {
        $this->fixture();
        $bookmarker = User::factory()->create();
        $bookmarker->bookmarks()->attach($this->listing->id);

        $this->actingAs($this->seller)
            ->put(route('listings.update', $this->listing), ListingFormValues::for($this->listing->refresh()))
            ->assertRedirect(route('listings.show', $this->listing));

        $this->assertSame('pending', DB::table('car_listings')->where('id', $this->listing->id)->value('status'));
        $this->assertListingStatusForGuest(404);
        $this->actingAs($this->buyer)->get(route('conversations.show', $this->conversation))->assertOk();
        $this->assertSendIsStored($this->buyer);

        $conversations = Conversation::query()->count();
        $this->actingAs($this->buyer)->get(route('conversations.create', $this->listing))->assertNotFound();
        $this->assertSame($conversations, Conversation::query()->count());
        $this->assertSame(1, DB::table('user_bookmarks')->where('user_id', $bookmarker->id)->where('car_listing_id', $this->listing->id)->count());
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function participantDeletions(): array
    {
        return [
            'the buyer deletes their own account' => ['buyer', 'self'],
            'the seller deletes their own account' => ['seller', 'self'],
            'an admin deletes the buyer' => ['buyer', 'admin'],
            'an admin deletes the seller' => ['seller', 'admin'],
        ];
    }

    #[DataProvider('participantDeletions')]
    public function test_deleting_a_participant_removes_the_conversation_for_the_survivor(string $deleted, string $by): void
    {
        $this->fixture();
        [$victim, $survivor] = $deleted === 'buyer' ? [$this->buyer, $this->seller] : [$this->seller, $this->buyer];
        $messageIds = $this->conversation->messages()->pluck('id')->all();
        $this->assertCount(3, $messageIds);
        $this->assertModelExists($this->report);
        foreach ([$this->buyersOther, $this->sellersOther] as $other) {
            $this->assertSame(1, $other->messages()->count());
        }
        $survivorsUnread = $this->unreadFor($survivor);

        if ($by === 'self') {
            $this->actingAs($victim)->delete(route('profile.destroy'), ['delete_password' => 'password'])->assertRedirect(route('home'));
        } else {
            $this->actingAs($this->admin)->delete(route('admin.users.destroy', $victim))->assertRedirect(route('admin.users'));
        }

        $this->assertModelMissing($victim);
        $this->assertModelMissing($this->conversation);
        $this->assertSame(0, Message::query()->whereIn('id', $messageIds)->count());
        $this->assertModelMissing($this->report);
        $this->assertSame($survivorsUnread - 1, $this->unreadFor($survivor));

        [$gone, $kept] = $deleted === 'buyer' ? [$this->buyersOther, $this->sellersOther] : [$this->sellersOther, $this->buyersOther];
        $this->assertModelMissing($gone);
        $this->assertModelExists($kept);
        $this->assertSame(1, $kept->messages()->count());

        $this->actingAs($survivor)->get(route('conversations.show', $this->conversation->id))->assertNotFound();
        $this->assertNotContains($this->conversation->id, $this->actingAs($survivor)->get(route('conversations.index'))->assertOk()->viewData('conversations')->pluck('id')->all());
    }

    private function fixture(?CarbonInterface $sellerSuspendedUntil = null): void
    {
        $this->buyer = User::factory()->create();
        $this->seller = $sellerSuspendedUntil === null
            ? User::factory()->create()
            : User::factory()->suspended($sellerSuspendedUntil)->create(['suspended_at' => now()->subDays(10), 'suspension_reason' => self::REASON]);
        $model = CarModel::factory()->create(['year_from' => 1950, 'year_to' => 1981]);
        $this->listing = CarListing::factory()->approved()->forCarModel($model)->for($this->seller, 'owner')->create(['year' => 1957]);

        $this->conversation = Conversation::factory()->for($this->listing, 'listing')->for($this->buyer, 'sender')->create(['last_message_at' => '2026-10-16 08:00:00']);
        $this->message($this->conversation, $this->seller, '2026-10-16 06:00:00', '2026-10-16 06:30:00');
        $this->message($this->conversation, $this->buyer, '2026-10-16 07:00:00');
        $newest = $this->message($this->conversation, $this->seller, '2026-10-16 08:00:00');
        $this->report = MessageReport::factory()->create(['message_id' => $newest->id]);

        $this->buyersOther = Conversation::factory()->for($this->buyer, 'sender')->create(['last_message_at' => '2026-10-16 05:00:00']);
        $this->message($this->buyersOther, $this->buyersOther->receiver, '2026-10-16 05:00:00');

        $this->sellersOther = Conversation::factory()->for($this->listing, 'listing')->create(['last_message_at' => '2026-10-16 04:00:00']);
        $this->message($this->sellersOther, $this->sellersOther->sender, '2026-10-16 04:00:00', '2026-10-16 04:30:00');
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

    private function suspend(User $user): void
    {
        $this->actingAs($this->admin)
            ->patch(route('admin.users.suspend', $user), ['reason' => self::REASON, 'duration' => '7_days'])
            ->assertRedirect(route('admin.users'));

        $this->assertSame('suspended', DB::table('users')->where('id', $user->id)->value('status'));
    }

    private function assertListingStatusForGuest(int $status): void
    {
        $this->app['auth']->forgetGuards();
        $this->flushSession();

        $this->get(route('listings.show', $this->listing))->assertStatus($status);
    }

    private function assertThreadShowsMessagesOf(User $viewer, User $author): void
    {
        $texts = $this->conversation->messages()->where('sender_id', $author->id)->pluck('message');
        $this->assertNotEmpty($texts);

        $response = $this->actingAs($viewer)->get(route('conversations.show', $this->conversation))->assertOk();

        foreach ($texts as $text) {
            $response->assertSee($text);
        }
    }

    private function assertSendIsStored(User $sender): void
    {
        $this->actingAs($sender)
            ->post(route('conversations.messages.store', $this->conversation), ['message' => self::TEXT])
            ->assertRedirect(route('conversations.show', $this->conversation));

        $message = Message::query()->latest('id')->firstOrFail();
        $this->assertSame(
            ['conversation_id' => $this->conversation->id, 'sender_id' => $sender->id, 'message' => self::TEXT, 'read_at' => null, 'created_at' => self::NOW],
            (array) DB::table('messages')->where('id', $message->id)->first(['conversation_id', 'sender_id', 'message', 'read_at', 'created_at']),
        );
        $this->assertSame(self::NOW, DB::table('conversations')->where('id', $this->conversation->id)->value('last_message_at'));
    }

    private function unreadFor(User $user): int
    {
        return $this->app->make(MessagingService::class)->unreadCountFor($user);
    }
}
