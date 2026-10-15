<?php

namespace Tests\Feature\Conversations;

use App\Enums\ListingStatus;
use App\Enums\UserStatus;
use App\Models\CarListing;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class StartConversationTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_a_guest_is_sent_to_login_from_both_routes(): void
    {
        $listing = CarListing::factory()->approved()->create();

        $this->get(route('conversations.create', $listing))->assertRedirect(route('login'));
        $this->post(route('conversations.store', $listing), ['message' => 'Is it still available?'])->assertRedirect(route('login'));
        $this->assertSame(0, Conversation::query()->count());
    }

    public function test_the_new_conversation_page_renders_the_thread_shell_and_writes_nothing(): void
    {
        $owner = User::factory()->create(['username' => 'havana seller']);
        $listing = CarListing::factory()->approved()->for($owner, 'owner')->create(['name' => 'Bel Air Sport']);

        $response = $this->actingAs(User::factory()->create())->get(route('conversations.create', $listing));

        $response->assertViewHas('conversation', null)
            ->assertViewHas('composerAction', route('conversations.store', $listing))
            ->assertSeeInOrder([
                '<h1',
                'Conversation with havana seller',
                'No messages yet. Write the first one below.',
                'action="'.route('conversations.store', $listing).'"',
            ], false)
            ->assertSee('Bel Air Sport');
        $this->assertSame(0, Conversation::query()->count());
    }

    public function test_the_back_link_falls_back_to_the_listing(): void
    {
        $listing = CarListing::factory()->approved()->create();

        $this->actingAs(User::factory()->create())
            ->get(route('conversations.create', $listing))
            ->assertViewHas('backUrl', route('listings.show', $listing));
    }

    public function test_a_buyer_with_a_conversation_is_redirected_to_it(): void
    {
        $conversation = Conversation::factory()->create();

        $this->actingAs($conversation->sender)
            ->get(route('conversations.create', $conversation->listing))
            ->assertRedirect(route('conversations.show', $conversation));
    }

    public function test_the_first_message_creates_one_conversation_and_lands_on_it_without_a_flash(): void
    {
        $listing = CarListing::factory()->approved()->create();
        $buyer = User::factory()->create();

        $response = $this->actingAs($buyer)->post(route('conversations.store', $listing), ['message' => 'Is it still available?']);

        $conversation = Conversation::query()->sole();
        $message = Message::query()->sole();
        $response->assertRedirect(route('conversations.show', $conversation).'#message-'.$message->id)
            ->assertSessionMissing('success');
        $this->assertSame([$buyer->id, $listing->user_id, $listing->id], [$conversation->sender_id, $conversation->receiver_id, $conversation->listing_id]);
        $this->assertSame([$buyer->id, 'Is it still available?'], [$message->sender_id, $message->message]);
    }

    public function test_a_second_first_message_appends_to_the_existing_conversation(): void
    {
        $conversation = Conversation::factory()->create();

        $this->actingAs($conversation->sender)
            ->post(route('conversations.store', $conversation->listing), ['message' => 'Sent from another tab.'])
            ->assertRedirect();

        $this->assertSame(1, Conversation::query()->count());
        $this->assertSame(1, $conversation->messages()->count());
    }

    public function test_server_controlled_fields_in_the_request_are_ignored(): void
    {
        $listing = CarListing::factory()->approved()->create();
        $buyer = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($buyer)->post(route('conversations.store', $listing), [
            'message' => 'Is it still available?',
            'sender_id' => $other->id,
            'receiver_id' => $other->id,
            'listing_id' => CarListing::factory()->approved()->create()->id,
            'conversation_id' => 999,
            'read_at' => '2026-01-01 00:00:00',
            'last_message_at' => '2020-01-01 00:00:00',
        ]);

        $conversation = Conversation::query()->sole();
        $message = Message::query()->sole();
        $this->assertSame([$buyer->id, $listing->user_id, $listing->id], [$conversation->sender_id, $conversation->receiver_id, $conversation->listing_id]);
        $this->assertSame([$conversation->id, $buyer->id, null], [$message->conversation_id, $message->sender_id, $message->read_at]);
        $this->assertTrue($conversation->last_message_at->equalTo($message->created_at));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function invalidMessages(): array
    {
        return [
            'four characters' => ['abcd', 'The message field must be at least 5 characters.'],
            '101 characters' => [str_repeat('a', 101), 'The message field must not be greater than 100 characters.'],
            'whitespace only' => ['       ', 'The message field is required.'],
        ];
    }

    #[DataProvider('invalidMessages')]
    public function test_an_invalid_first_message_returns_to_the_page_with_the_error_and_writes_nothing(string $text, string $error): void
    {
        $listing = CarListing::factory()->approved()->create();

        $this->actingAs(User::factory()->create())
            ->from('https://evil.example/')
            ->post(route('conversations.store', $listing), ['message' => $text])
            ->assertRedirect(route('conversations.create', $listing))
            ->assertSessionHasErrors(['message' => $error]);

        $this->assertSame(0, Conversation::query()->count());
    }

    public function test_the_rejected_message_is_refilled_with_its_error_and_counter(): void
    {
        $listing = CarListing::factory()->approved()->create();

        $response = $this->actingAs(User::factory()->create())
            ->followingRedirects()
            ->post(route('conversations.store', $listing), ['message' => 'abcd']);

        $response->assertSee('value="abcd"', false)
            ->assertSee('aria-invalid="true"', false)
            ->assertSee('aria-describedby="message-counter message-error"', false)
            ->assertSee('<p id="message-error" class="text-xs text-red-deep">The message field must be at least 5 characters.</p>', false)
            ->assertSee('4 / 100 · min 5');
    }

    public function test_the_owner_is_refused_with_403_before_validation(): void
    {
        $listing = CarListing::factory()->approved()->create();
        $this->actingAs($listing->owner);

        $this->get(route('conversations.create', $listing))->assertForbidden();
        $this->post(route('conversations.store', $listing), [])->assertForbidden()->assertSessionDoesntHaveErrors();
        $this->assertSame(0, Conversation::query()->count());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function hiddenListings(): array
    {
        return [
            'pending' => ['pending'],
            'rejected' => ['rejected'],
            'suspended owner' => ['suspended owner'],
        ];
    }

    #[DataProvider('hiddenListings')]
    public function test_a_non_public_listing_is_404_for_a_stranger_before_validation(string $state): void
    {
        $listing = match ($state) {
            'pending' => CarListing::factory()->create(),
            'rejected' => CarListing::factory()->rejected()->create(),
            'suspended owner' => CarListing::factory()->approved()->for(User::factory()->suspended(), 'owner')->create(),
        };
        $this->actingAs(User::factory()->create());

        $this->get(route('conversations.create', $listing))->assertNotFound();
        $this->post(route('conversations.store', $listing), [])->assertNotFound()->assertSessionDoesntHaveErrors();
        $this->assertSame(0, Conversation::query()->count());
    }

    #[DataProvider('hiddenListings')]
    public function test_a_buyer_with_a_conversation_cannot_reopen_it_from_a_hidden_listing(string $state): void
    {
        $conversation = Conversation::factory()->create();
        match ($state) {
            'pending' => $conversation->listing->forceFill(['status' => ListingStatus::Pending])->save(),
            'rejected' => $conversation->listing->forceFill(['status' => ListingStatus::Rejected, 'rejection_reason' => 'Blurry photos.'])->save(),
            'suspended owner' => $conversation->receiver->forceFill(['status' => UserStatus::Suspended])->save(),
        };

        $this->actingAs($conversation->sender)
            ->get(route('conversations.create', $conversation->listing))
            ->assertNotFound();
    }

    /**
     * @return array<string, array{string}>
     */
    public static function gatedViewers(): array
    {
        return [
            'admin' => ['admin'],
        ];
    }

    #[DataProvider('gatedViewers')]
    public function test_admins_get_the_403_page(string $viewerKind): void
    {
        $listing = CarListing::factory()->approved()->create();
        $this->actingAs(User::factory()->admin()->create());

        $this->get(route('conversations.create', $listing))->assertForbidden();
        $this->post(route('conversations.store', $listing), [])->assertForbidden()->assertSessionDoesntHaveErrors();
    }

    public function test_a_suspended_user_in_a_live_session_is_signed_out_on_both_routes(): void
    {
        $listing = CarListing::factory()->approved()->create();
        $user = User::factory()->suspended()->create();

        $this->actingAs($user)->get(route('conversations.create', $listing))->assertRedirect(route('login'));
        $this->actingAs($user)->post(route('conversations.store', $listing), ['message' => 'Is it still available?'])->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertDatabaseCount('conversations', 0);
    }

    public function test_an_unknown_or_non_numeric_listing_is_404(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/listings/999999/message')->assertNotFound();
        $this->get('/listings/abc/message')->assertNotFound();
    }
}
