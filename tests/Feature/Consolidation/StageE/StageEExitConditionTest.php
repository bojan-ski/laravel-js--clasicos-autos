<?php

namespace Tests\Feature\Consolidation\StageE;

use App\Events\MessageSent;
use App\Models\CarListing;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\Support\ApplicationRoutes;
use Tests\TestCase;

class StageEExitConditionTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const string FIRST = 'Is the Bel Air still available?';

    private const string SECOND = 'I can come to Havana on Saturday.';

    private const string REPLY = 'Yes, Saturday works. Bring cash.';

    private User $buyer;

    private User $seller;

    private User $admin;

    private CarListing $listing;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->freezeTime();
        Storage::fake('public');
        Event::fake([MessageSent::class]);

        $this->buyer = User::factory()->create();
        $this->seller = User::factory()->create();
        $this->admin = User::factory()->admin()->create();
        $this->listing = CarListing::factory()->approved()->for($this->seller, 'owner')->create();
    }

    public function test_a_buyer_messages_a_seller_who_reads_and_replies_while_the_admin_reads_everything_and_marks_nothing(): void
    {
        $this->as($this->buyer)->get(route('conversations.create', $this->listing))->assertOk();
        $this->assertSame(0, Conversation::query()->count());
        $this->as($this->seller)->get(route('conversations.create', $this->listing))
            ->assertForbidden()
            ->assertSee(__('app.errors.403.title'));
        $this->asGuest()->get(route('conversations.create', $this->listing))->assertRedirect(route('login'));

        $this->as($this->buyer)->post(route('conversations.store', $this->listing), ['message' => self::FIRST])->assertRedirect();
        $conversation = Conversation::query()->sole();
        $this->assertSame(1, $conversation->messages()->count());
        $this->assertSame([$this->seller->id], $this->recipientsOfDispatches());
        $this->as($this->buyer)->post(route('conversations.store', $this->listing), ['message' => self::SECOND])
            ->assertRedirect(route('conversations.show', $conversation));
        $this->assertSame(1, Conversation::query()->count());
        $this->assertSame(2, $conversation->messages()->count());
        $this->assertSame([$this->seller->id, $this->seller->id], $this->recipientsOfDispatches());

        $this->assertHeaderCount($this->as($this->seller)->get(route('home')), 2);
        $this->as($this->seller)->get(route('conversations.show', $conversation))->assertOk()->assertSee([self::FIRST, self::SECOND]);
        $this->assertNotNull($this->messageWith(self::FIRST)->read_at);
        $this->assertNotNull($this->messageWith(self::SECOND)->read_at);
        $this->assertHeaderCount($this->as($this->seller)->get(route('home')), 0);

        $this->as($this->seller)->followingRedirects()
            ->post(route('conversations.messages.store', $conversation), ['message' => self::REPLY])
            ->assertOk()
            ->assertSee(self::REPLY);
        $reply = $this->messageWith(self::REPLY);
        $this->assertSame([$this->seller->id, $this->seller->id, $this->buyer->id], $this->recipientsOfDispatches());
        $this->assertSame(1, $this->lastDispatch()->broadcastWith()['unread_count']);
        $this->assertNull($reply->read_at);
        $this->assertHeaderCount($this->as($this->seller)->get(route('home')), 0);

        $others = Conversation::factory()->create();
        $othersMessage = Message::factory()->for($others)->create();
        $readMarks = $this->readMarks();
        $this->as($this->admin)->get(route('conversations.index'))
            ->assertOk()
            ->assertSee([route('conversations.show', $conversation), route('conversations.show', $others)])
            ->assertDontSee(['data-unread-count', 'data-unread-badge', 'data-live-unread', ' data-unread>'], false);
        $this->as($this->admin)->get(route('conversations.show', $conversation))->assertOk()->assertSee(self::REPLY);
        $this->as($this->admin)->get(route('conversations.show', $others))->assertOk()->assertSee($othersMessage->message);
        $this->as($this->admin)->post(route('conversations.read', $conversation))->assertNoContent();
        $this->assertSame($readMarks, $this->readMarks());
        $this->assertCount(3, $this->recipientsOfDispatches());

        $this->as($this->buyer)->get(route('conversations.index'))
            ->assertOk()
            ->assertSee('data-conversation-card="'.$conversation->id.'" data-unread-count="1" data-last-message-id="'.$reply->id.'" data-unread', false)
            ->assertSee('>'.__('app.conversations.index.unread', ['count' => 1]).'</p>', false)
            ->assertSee('<span data-unread-badge-text>1</span>', false);
        $this->as($this->buyer)->get(route('conversations.show', $conversation))->assertOk();
        $this->assertNotNull($reply->refresh()->read_at);

        $first = $this->messageWith(self::FIRST);
        $this->as($this->buyer)->delete(route('conversations.messages.destroy', [$conversation, $first]))
            ->assertRedirect(route('conversations.show', $conversation))
            ->assertSessionHas('success', __('app.conversations.deleted'));
        $this->assertModelMissing($first);
        $this->as($this->buyer)->delete(route('conversations.messages.destroy', [$conversation, $reply]))
            ->assertForbidden()
            ->assertSee(__('app.errors.403.title'));
        $this->assertModelExists($reply);
        $this->as($this->admin)->delete(route('conversations.messages.destroy', [$conversation, $reply]))
            ->assertRedirect(route('conversations.show', $conversation));
        $this->assertModelMissing($reply);
        $this->assertCount(3, $this->recipientsOfDispatches());
    }

    public function test_a_stranger_gets_the_not_found_page_on_the_thread_and_not_found_on_the_read_route(): void
    {
        $conversation = $this->conversationWithMessages();
        $readMarks = $this->readMarks();
        $stranger = User::factory()->create();

        $this->as($stranger)->get(route('conversations.show', $conversation))
            ->assertNotFound()
            ->assertSee(__('app.errors.404.title'))
            ->assertDontSee(self::FIRST);
        $this->as($stranger)->post(route('conversations.read', $conversation))->assertNotFound();
        $this->assertSame($readMarks, $this->readMarks());
    }

    public function test_deleting_the_listing_removes_its_conversation_and_messages(): void
    {
        $conversation = $this->conversationWithMessages();
        $kept = Message::factory()->for(Conversation::factory())->create();

        $this->as($this->seller)->delete(route('listings.destroy', $this->listing))->assertRedirect(route('profile'));

        $this->assertModelMissing($conversation);
        $this->assertSame(0, Message::query()->where('conversation_id', $conversation->id)->count());
        $this->assertModelExists($kept);
    }

    public function test_the_broadcast_auth_route_is_registered_on_web_without_a_name(): void
    {
        $route = RouteFacade::getRoutes()->match(request()->create('/broadcasting/auth', 'POST'));

        $this->assertInstanceOf(Route::class, $route);
        $this->assertSame(['GET', 'POST', 'HEAD'], $route->methods());
        $this->assertSame(['web'], $route->gatherMiddleware());
        $this->assertNull($route->getName());
        $this->assertNotContains($route, ApplicationRoutes::named());
    }

    private function conversationWithMessages(): Conversation
    {
        $this->as($this->buyer)->post(route('conversations.store', $this->listing), ['message' => self::FIRST])->assertRedirect();
        $conversation = Conversation::query()->sole();
        $this->as($this->seller)->post(route('conversations.messages.store', $conversation), ['message' => self::REPLY])->assertRedirect();

        return $conversation;
    }

    private function assertHeaderCount(TestResponse $response, int $count): void
    {
        $response->assertOk()->assertSee('<span data-unread-badge-text>'.$count.'</span>', false);

        if ($count === 0) {
            $response->assertSee('<span data-unread-badge hidden', false);
        } else {
            $response->assertSee('<span data-unread-badge data-nav-count', false);
        }
    }

    private function messageWith(string $text): Message
    {
        return Message::query()->where('message', $text)->sole();
    }

    /**
     * @return array<int, string|null>
     */
    private function readMarks(): array
    {
        return Message::query()->orderBy('id')->pluck('read_at', 'id')->map(fn ($readAt): ?string => $readAt?->toJSON())->all();
    }

    /**
     * @return list<int>
     */
    private function recipientsOfDispatches(): array
    {
        return Event::dispatched(MessageSent::class)->map(fn (array $dispatch): int => $dispatch[0]->recipient->id)->values()->all();
    }

    private function lastDispatch(): MessageSent
    {
        return Event::dispatched(MessageSent::class)->last()[0];
    }

    private function as(User $user): static
    {
        $this->app['auth']->forgetGuards();

        return $this->actingAs($user);
    }

    private function asGuest(): static
    {
        $this->app['auth']->forgetGuards();

        return $this;
    }
}
