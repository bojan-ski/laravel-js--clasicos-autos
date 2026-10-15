<?php

namespace Tests\Feature\Conversations;

use App\Enums\ListingStatus;
use App\Enums\UserStatus;
use App\Models\CarListing;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ConversationThreadPageTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const string OWN = 'ml-auto bg-card-unread border-red rotate-[0.4deg]';

    private const string OTHER = 'bg-card border-rule -rotate-[0.4deg]';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Storage::fake('public');
    }

    public function test_a_guest_is_sent_to_login(): void
    {
        $conversation = Conversation::factory()->create();

        $this->get(route('conversations.show', $conversation))->assertRedirect(route('login'));
    }

    public function test_a_stranger_gets_404_for_an_existing_and_an_unknown_conversation(): void
    {
        $conversation = Conversation::factory()->create();
        $this->actingAs(User::factory()->create());

        $this->get(route('conversations.show', $conversation))->assertNotFound();
        $this->get('/conversations/999999')->assertNotFound();
        $this->get('/conversations/abc')->assertNotFound();
    }

    public function test_each_participant_sees_the_other_ones_name_in_the_heading(): void
    {
        $conversation = $this->conversationBetween('cuban buyer', 'havana seller');

        $this->show($conversation, $conversation->sender)->assertSeeInOrder(['<h1', 'Conversation with havana seller'], false);
        $this->show($conversation, $conversation->receiver)->assertSeeInOrder(['<h1', 'Conversation with cuban buyer'], false);
    }

    public function test_an_admin_sees_both_names_in_the_heading(): void
    {
        $conversation = $this->conversationBetween('cuban buyer', 'havana seller');

        $this->show($conversation, User::factory()->admin()->create())
            ->assertSeeInOrder(['<h1', 'Conversation between cuban buyer and havana seller'], false);
    }

    public function test_messages_render_oldest_first_with_ties_broken_by_id_and_human_times(): void
    {
        $this->travelTo('2026-10-15 12:00:00');
        $conversation = Conversation::factory()->create();
        $tieFirst = $this->message($conversation, $conversation->sender, 'Tie broken first', '2026-10-15 10:00:00');
        $this->message($conversation, $conversation->receiver, 'Tie broken second', '2026-10-15 10:00:00');
        $this->message($conversation, $conversation->sender, 'Newest message', '2026-10-15 11:30:00');
        $this->message($conversation, $conversation->receiver, 'Oldest message', '2026-10-15 08:00:00');

        $response = $this->show($conversation, $conversation->sender);

        $response->assertSeeInOrder(['Oldest message', 'Tie broken first', 'Tie broken second', 'Newest message'])
            ->assertSee('<li id="message-'.$tieFirst->id.'"', false)
            ->assertSee('<time datetime="2026-10-15T10:00:00+00:00">2 hours ago</time>', false)
            ->assertSee('<time datetime="2026-10-15T11:30:00+00:00">30 minutes ago</time>', false);
    }

    public function test_a_participant_sees_their_own_bubbles_right_and_the_other_partys_left(): void
    {
        $conversation = Conversation::factory()->create();
        $own = $this->message($conversation, $conversation->sender, 'Sent by the buyer');
        $other = $this->message($conversation, $conversation->receiver, 'Sent by the seller');

        $html = $this->show($conversation, $conversation->sender)->getContent();

        $this->assertStringContainsString(self::OWN, $this->bubble($html, $own));
        $this->assertStringContainsString(self::OTHER, $this->bubble($html, $other));
        $this->assertStringNotContainsString('ml-auto', $this->bubble($html, $other));
    }

    public function test_a_participant_hears_the_sender_as_an_sr_only_prefix(): void
    {
        $conversation = $this->conversationBetween('cuban buyer', 'havana seller');
        $this->message($conversation, $conversation->sender, 'Sent by the buyer');
        $this->message($conversation, $conversation->receiver, 'Sent by the seller');

        $this->show($conversation, $conversation->sender)
            ->assertSee('<span class="sr-only">You: </span>Sent by the buyer', false)
            ->assertSee('<span class="sr-only">havana seller: </span>Sent by the seller', false);
    }

    public function test_an_admin_sees_usernames_and_the_seller_right_aligned(): void
    {
        $conversation = $this->conversationBetween('cuban buyer', 'havana seller');
        $fromBuyer = $this->message($conversation, $conversation->sender, 'Sent by the buyer');
        $fromSeller = $this->message($conversation, $conversation->receiver, 'Sent by the seller');

        $html = $this->show($conversation, User::factory()->admin()->create())->getContent();

        $this->assertStringContainsString(self::OTHER, $this->bubble($html, $fromBuyer));
        $this->assertStringContainsString(self::OWN, $this->bubble($html, $fromSeller));
        $this->assertStringContainsString('<span class="wrap-break-word">cuban buyer</span>', $this->bubble($html, $fromBuyer));
        $this->assertStringContainsString('<span class="wrap-break-word">havana seller</span>', $this->bubble($html, $fromSeller));
        $this->assertStringContainsString('<p class="wrap-break-word">Sent by the buyer</p>', $this->bubble($html, $fromBuyer));
    }

    public function test_message_text_is_escaped(): void
    {
        $conversation = Conversation::factory()->create();
        $this->message($conversation, $conversation->receiver, '<script>alert(1)</script>');

        $this->show($conversation, $conversation->sender)
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    }

    public function test_the_sidebar_shows_the_photo_name_price_and_link_of_a_viewable_listing(): void
    {
        $listing = CarListing::factory()->approved()->withImages()->create(['name' => 'Bel Air Sport', 'price' => 25000]);
        $conversation = Conversation::factory()->for($listing, 'listing')->create();

        $this->show($conversation, $conversation->sender)
            ->assertSeeInOrder([
                'src="'.$listing->images()->sole()->thumbUrl().'" alt="" width="320" height="240"',
                'Regarding',
                'Bel Air Sport',
                '$25,000',
                'href="'.route('listings.show', $listing).'"',
                'View listing',
            ], false)
            ->assertDontSee('This listing is no longer available.');
    }

    public function test_a_listing_without_a_photo_omits_the_image(): void
    {
        $conversation = Conversation::factory()->create();

        $this->show($conversation, $conversation->sender)
            ->assertSee('View listing')
            ->assertDontSee('photo-grade', false);
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
    public function test_the_buyer_sees_only_the_name_and_a_note_once_the_listing_is_hidden(string $state): void
    {
        $listing = CarListing::factory()->approved()->withImages()->create(['name' => 'Bel Air Sport', 'price' => 25000]);
        $conversation = Conversation::factory()->for($listing, 'listing')->create();
        $this->hide($conversation, $state);

        $this->show($conversation, $conversation->sender)
            ->assertSee('Bel Air Sport')
            ->assertSee('This listing is no longer available.')
            ->assertDontSee('$25,000')
            ->assertDontSee('View listing')
            ->assertDontSee('href="'.route('listings.show', $listing).'"', false)
            ->assertDontSee('photo-grade', false);
    }

    public function test_the_seller_still_sees_their_hidden_listing_in_full(): void
    {
        $conversation = Conversation::factory()->create();
        $this->hide($conversation, 'pending');

        $this->show($conversation, $conversation->receiver)
            ->assertSee('View listing')
            ->assertDontSee('This listing is no longer available.');
    }

    public function test_a_participant_gets_the_composer_with_its_attributes_and_counter(): void
    {
        $conversation = Conversation::factory()->create();

        $this->show($conversation, $conversation->sender)
            ->assertViewHas('composerAction', route('conversations.messages.store', $conversation))
            ->assertSee('action="'.route('conversations.messages.store', $conversation).'" data-composer data-sticky-bar', false)
            ->assertSee('<label for="message" class="text-xs text-ink/70">Your message</label>', false)
            ->assertSee('required minlength="5" maxlength="100"', false)
            ->assertSee('placeholder="Type your message..."', false)
            ->assertSee('aria-describedby="message-counter"', false)
            ->assertSee('<p id="message-counter" class="text-xs text-muted tabular-nums" data-composer-counter', false)
            ->assertSee('data-template=":count / 100 · min 5" data-min="5" data-max="100">0 / 100 · min 5</p>', false)
            ->assertSee('aria-disabled:cursor-not-allowed aria-disabled:opacity-45" data-composer-submit="data-composer-submit">', false);
    }

    public function test_below_md_the_composer_is_a_sticky_bar_and_the_mobile_header_sticks_to_the_top(): void
    {
        $conversation = Conversation::factory()->create();

        $this->show($conversation, $conversation->sender)
            ->assertSee('data-composer data-sticky-bar', false)
            ->assertSee('class="sticky bottom-(--cookie-banner-h,0px) z-10 -mx-5', false)
            ->assertSee('class="sticky top-0 z-10 flex items-center gap-2 border-b border-rule bg-paper px-2 py-2 md:hidden"', false);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function readOnlyViewers(): array
    {
        return [
            'admin' => ['admin'],
        ];
    }

    #[DataProvider('readOnlyViewers')]
    public function test_admins_read_without_a_composer(string $viewerKind): void
    {
        $conversation = Conversation::factory()->create();
        $this->message($conversation, $conversation->receiver, 'A message to read');
        $viewer = User::factory()->admin()->create();

        $this->show($conversation, $viewer)
            ->assertViewHas('composerAction', null)
            ->assertSee('A message to read')
            ->assertDontSee('data-composer', false)
            ->assertDontSee('role="log"', false)
            ->assertDontSee('data-live-', false);
    }

    public function test_a_suspended_participant_in_a_live_session_is_signed_out_of_the_thread(): void
    {
        $conversation = Conversation::factory()->create();
        $this->message($conversation, $conversation->receiver, 'A message to read');

        $this->actingAs($this->suspend($conversation->sender))
            ->get(route('conversations.show', $conversation))
            ->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertNull(Message::query()->sole()->read_at);
    }

    public function test_a_participant_who_may_reply_gets_the_live_thread_hooks(): void
    {
        $conversation = $this->conversationBetween('buyer.one', 'seller.one');
        $this->message($conversation, $conversation->receiver, 'A message from the seller');

        $this->show($conversation, $conversation->sender)
            ->assertSee('<div role="log" aria-label="Messages" data-live-thread="'.$conversation->id.'" data-mark-read-url="'.route('conversations.read', $conversation).'">', false)
            ->assertSeeInOrder(['role="log"', '<ol role="list" class="flex flex-col gap-4">', 'A message from the seller', '</ol>', '<template data-live-bubble>'], false);
    }

    public function test_the_live_bubble_template_is_an_other_style_bubble_without_actions(): void
    {
        $conversation = $this->conversationBetween('buyer.one', 'seller.one');

        $html = $this->show($conversation, $conversation->sender)->getContent();
        $start = strpos($html, '<template data-live-bubble>');
        $template = substr($html, $start, strpos($html, '</template>', $start) - $start);

        $this->assertStringContainsString('<li class="w-fit max-w-110 scroll-mt-16 border px-4 py-3 bg-card border-rule -rotate-[0.4deg]">', $template);
        $this->assertStringContainsString('<span class="sr-only">seller.one: </span><span data-live-bubble-text></span>', $template);
        $this->assertStringContainsString('<div class="mt-1 flex flex-wrap items-center gap-x-3 text-[11px] text-muted">', $template);
        $this->assertStringContainsString('<time data-live-bubble-time></time>', $template);
        $this->assertStringNotContainsString('<dialog', $template);
        $this->assertStringNotContainsString('<button', $template);
    }

    public function test_a_new_conversation_page_has_no_live_thread(): void
    {
        $listing = CarListing::factory()->approved()->create();

        $this->actingAs(User::factory()->create())
            ->get(route('conversations.create', $listing))
            ->assertOk()
            ->assertSee('data-composer', false)
            ->assertDontSee('data-live-thread', false)
            ->assertDontSee('data-live-bubble', false);
    }

    public function test_a_participant_gets_one_delete_dialog_per_own_message(): void
    {
        $conversation = Conversation::factory()->create();
        $first = $this->message($conversation, $conversation->sender, 'First own message');
        $second = $this->message($conversation, $conversation->sender, 'Second own message');
        $other = $this->message($conversation, $conversation->receiver, 'Seller message here');

        $html = $this->show($conversation, $conversation->sender)->getContent();

        $this->assertSame(1, substr_count($html, '<dialog id="delete-message-'.$first->id.'"'));
        $this->assertSame(1, substr_count($html, '<dialog id="delete-message-'.$second->id.'"'));
        $this->assertStringNotContainsString('delete-message-'.$other->id, $html);
        $this->assertSame(2, substr_count($html, 'Are you sure you want to delete the message?'));
        $this->assertStringContainsString('action="'.route('conversations.messages.destroy', [$conversation, $first]).'"', $html);
        $this->assertStringContainsString('This can&#039;t be undone.', $html);
    }

    public function test_an_admin_gets_a_delete_dialog_on_every_message(): void
    {
        $conversation = Conversation::factory()->create();
        $this->message($conversation, $conversation->sender, 'Sent by the buyer');
        $this->message($conversation, $conversation->receiver, 'Sent by the seller');

        $this->assertSame(2, substr_count(
            $this->show($conversation, User::factory()->admin()->create())->getContent(),
            'Are you sure you want to delete the message?',
        ));
    }

    public function test_an_emptied_thread_shows_its_empty_line_above_a_hidden_live_list(): void
    {
        $conversation = Conversation::factory()->create();

        $this->show($conversation, $conversation->sender)
            ->assertSeeInOrder([
                '<p data-live-empty class="text-muted italic">There are no messages in this conversation.</p>',
                '<div role="log" aria-label="Messages" data-live-thread="'.$conversation->id.'"',
                '<ol role="list" class="flex flex-col gap-4" hidden>',
            ], false);
    }

    public function test_a_read_only_viewer_gets_a_named_list_of_messages(): void
    {
        $conversation = Conversation::factory()->create();
        $this->message($conversation, $conversation->receiver, 'A message to read');

        $this->show($conversation, User::factory()->admin()->create())
            ->assertSee('<ol role="list" class="flex flex-col gap-4" aria-label="Messages">', false);
    }

    public function test_the_mobile_headers_listing_link_is_a_44_px_target(): void
    {
        $conversation = Conversation::factory()->create();

        $this->show($conversation, $conversation->sender)
            ->assertSee('<a href="'.route('listings.show', $conversation->listing).'" class="-my-3.5 flex min-h-11 items-center text-xs text-muted underline"><span class="min-w-0 truncate">', false);
    }

    public function test_an_emptied_thread_shows_a_read_only_viewer_no_list(): void
    {
        $conversation = Conversation::factory()->create();

        $this->show($conversation, User::factory()->admin()->create())
            ->assertSee('<p class="text-muted italic">There are no messages in this conversation.</p>', false)
            ->assertDontSee('<ol', false);
    }

    public function test_the_back_link_is_the_conversations_list_whatever_the_referer(): void
    {
        $conversation = Conversation::factory()->create();

        $this->actingAs($conversation->sender)
            ->from(route('conversations.create', $conversation->listing))
            ->get(route('conversations.show', $conversation))
            ->assertViewHas('backUrl', route('conversations.index'));
    }

    public function test_a_participants_visit_marks_only_the_other_partys_messages_read(): void
    {
        $conversation = Conversation::factory()->create();
        $fromSeller = $this->message($conversation, $conversation->receiver, 'Unread from seller');
        $fromBuyer = $this->message($conversation, $conversation->sender, 'Sent by the buyer');

        $this->show($conversation, $conversation->sender);

        $this->assertNotNull($fromSeller->fresh()->read_at);
        $this->assertNull($fromBuyer->fresh()->read_at);
    }

    public function test_an_admins_visit_leaves_every_message_unread(): void
    {
        $conversation = Conversation::factory()->create();
        $this->message($conversation, $conversation->receiver, 'Unread from seller');
        $this->message($conversation, $conversation->sender, 'Unread from buyer');

        $this->show($conversation, User::factory()->admin()->create());

        $this->assertSame(0, Message::query()->whereNotNull('read_at')->count());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function threadViewers(): array
    {
        return [
            'participant' => ['participant'],
            'admin' => ['admin'],
        ];
    }

    #[DataProvider('threadViewers')]
    public function test_a_thread_renders_without_lazy_loading_and_with_a_constant_query_count(string $viewerKind): void
    {
        $small = Conversation::factory()->for(CarListing::factory()->approved()->withImages(), 'listing')->create();
        $large = Conversation::factory()->for(CarListing::factory()->approved()->withImages(), 'listing')->create();
        Message::factory()->for($small)->create();
        foreach (range(1, 5) as $index) {
            Message::factory()->for($large)->create();
            Message::factory()->for($large)->create(['sender_id' => $large->receiver_id]);
        }
        $admin = User::factory()->admin()->create();

        $smallCount = $this->queriesFor($small, $viewerKind === 'admin' ? $admin : $small->sender);
        $largeCount = $this->queriesFor($large, $viewerKind === 'admin' ? $admin : $large->sender);

        $this->assertSame($smallCount, $largeCount);
    }

    private function queriesFor(Conversation $conversation, User $viewer): int
    {
        $this->actingAs($viewer);
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->get(route('conversations.show', $conversation))->assertOk();

        $count = count(DB::getQueryLog());
        DB::disableQueryLog();
        $this->app['auth']->forgetGuards();

        return $count;
    }

    private function show(Conversation $conversation, User $viewer): TestResponse
    {
        $response = $this->actingAs($viewer)->get(route('conversations.show', $conversation));
        $this->app['auth']->forgetGuards();

        return $response->assertOk();
    }

    private function conversationBetween(string $buyer, string $seller): Conversation
    {
        $listing = CarListing::factory()->approved()->for(User::factory()->create(['username' => $seller]), 'owner')->create();

        return Conversation::factory()->for($listing, 'listing')->for(User::factory()->create(['username' => $buyer]), 'sender')->create();
    }

    private function message(Conversation $conversation, User $sender, string $text, ?string $sentAt = null): Message
    {
        $sentAt ??= now();

        return Message::factory()->for($conversation)->create([
            'sender_id' => $sender->id,
            'message' => $text,
            'created_at' => $sentAt,
            'updated_at' => $sentAt,
        ]);
    }

    private function bubble(string $html, Message $message): string
    {
        $start = strpos($html, '<li id="message-'.$message->id.'"');
        $this->assertNotFalse($start);

        return substr($html, $start, strpos($html, '</li>', $start) - $start);
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
