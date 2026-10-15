<?php

namespace Tests\Feature\Conversations;

use App\Enums\ReportOutcome;
use App\Enums\ReportReason;
use App\Models\CarListing;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\MessageReport;
use App\Models\User;
use App\Services\MessageReportService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ReportMessageTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const string NOTE = 'He asked me to wire a deposit before any viewing.';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_a_participant_reports_the_other_partys_message_and_returns_to_it(): void
    {
        [$conversation, $message] = $this->sellerMessage('Send the deposit to this account first.');

        $this->report($conversation->sender, $message, ['reason' => 'scam', 'note' => '  '.self::NOTE.'  '])
            ->assertRedirect(route('conversations.show', $conversation).'#message-'.$message->id)
            ->assertSessionHas('success', 'Thank you. An admin will review this message.');

        $this->assertSame([
            'conversation_id' => $conversation->id,
            'message_id' => $message->id,
            'message_text' => 'Send the deposit to this account first.',
            'reporter_id' => $conversation->sender_id,
            'reported_user_id' => $conversation->receiver_id,
            'reason' => 'scam',
            'note' => self::NOTE,
            'status' => 'open',
            'outcome' => null,
            'resolved_by' => null,
            'resolved_at' => null,
        ], $this->reportRow());
    }

    public function test_the_seller_reports_the_buyers_message_too(): void
    {
        $conversation = Conversation::factory()->create();
        $message = Message::factory()->for($conversation)->create();

        $this->report($conversation->receiver, $message, ['reason' => 'abusive'])->assertSessionHas('success');

        $this->assertSame($conversation->sender_id, $this->reportRow()['reported_user_id']);
    }

    public function test_a_blank_note_is_stored_as_null(): void
    {
        [$conversation, $message] = $this->sellerMessage();

        $this->report($conversation->sender, $message, ['reason' => 'spam', 'note' => '   ']);

        $this->assertNull($this->reportRow()['note']);
    }

    public function test_server_controlled_keys_in_the_post_are_ignored(): void
    {
        [$conversation, $message] = $this->sellerMessage('The real text.');
        $other = User::factory()->create();

        $this->report($conversation->sender, $message, [
            'reason' => 'spam',
            'status' => 'resolved',
            'outcome' => 'dismissed',
            'reporter_id' => $other->id,
            'reported_user_id' => $other->id,
            'message_text' => 'Forged text.',
            'resolved_by' => $other->id,
        ])->assertSessionHas('success');

        $row = $this->reportRow();
        $this->assertSame(['open', null, $conversation->sender_id, $conversation->receiver_id, 'The real text.', null], [
            $row['status'], $row['outcome'], $row['reporter_id'], $row['reported_user_id'], $row['message_text'], $row['resolved_by'],
        ]);
    }

    /**
     * @return array<string, array{bool}>
     */
    public static function earlierReports(): array
    {
        return [
            'still open' => [false],
            'already dismissed' => [true],
        ];
    }

    #[DataProvider('earlierReports')]
    public function test_a_second_report_of_the_same_message_stores_nothing_and_flashes_the_error(bool $resolved): void
    {
        [$conversation, $message] = $this->sellerMessage();
        $factory = MessageReport::factory();
        ($resolved ? $factory->resolved(ReportOutcome::Dismissed) : $factory)->create(['message_id' => $message->id]);

        $this->report($conversation->sender, $message, ['reason' => 'spam'])
            ->assertRedirect(route('conversations.show', $conversation).'#message-'.$message->id)
            ->assertSessionHas('error', 'You already reported this message')
            ->assertSessionMissing('success');

        $this->assertSame(1, MessageReport::query()->count());
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function invalidPayloads(): array
    {
        return [
            'missing reason' => [['note' => 'Some note.'], 'The reason field is required.'],
            'unknown reason' => [['reason' => 'rude'], 'The selected reason is invalid.'],
            'note over 500' => [['reason' => 'spam', 'note' => str_repeat('a', 501)], 'The note field must not be greater than 500 characters.'],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    #[DataProvider('invalidPayloads')]
    public function test_an_invalid_report_returns_to_the_message_with_the_first_error_and_stores_nothing(array $payload, string $error): void
    {
        [$conversation, $message] = $this->sellerMessage();

        $this->report($conversation->sender, $message, $payload)
            ->assertRedirect(route('conversations.show', $conversation).'#message-'.$message->id)
            ->assertSessionHas('error', $error)
            ->assertSessionDoesntHaveErrors();

        $this->assertSame(0, MessageReport::query()->count());
    }

    public function test_a_note_of_exactly_500_characters_is_accepted(): void
    {
        [$conversation, $message] = $this->sellerMessage();

        $this->report($conversation->sender, $message, ['reason' => 'other', 'note' => str_repeat('a', 500)])->assertSessionHas('success');

        $this->assertSame(500, mb_strlen($this->reportRow()['note']));
    }

    /**
     * @return array<string, array{string, int}>
     */
    public static function refusedReporters(): array
    {
        return [
            'the sender of the message' => ['seller', 403],
            'an admin' => ['admin', 403],
            'a stranger' => ['stranger', 404],
        ];
    }

    #[DataProvider('refusedReporters')]
    public function test_a_refused_reporter_is_turned_away_before_validation(string $kind, int $status): void
    {
        [$conversation, $message] = $this->sellerMessage();
        $viewer = match ($kind) {
            'seller' => $conversation->receiver,
            'admin' => User::factory()->admin()->create(),
            'stranger' => User::factory()->create(),
        };

        $this->report($viewer, $message, ['reason' => 'not-a-reason'])
            ->assertStatus($status)
            ->assertSessionDoesntHaveErrors()
            ->assertSessionMissing('error');

        $this->assertSame(0, MessageReport::query()->count());
    }

    public function test_a_guest_is_sent_to_login(): void
    {
        [$conversation, $message] = $this->sellerMessage();

        $this->post(route('conversations.messages.report', [$conversation, $message]), ['reason' => 'spam'])
            ->assertRedirect(route('login'));

        $this->assertSame(0, MessageReport::query()->count());
    }

    public function test_a_suspended_participant_is_signed_out(): void
    {
        [$conversation, $message] = $this->sellerMessage();
        $conversation->sender->forceFill(['status' => 'suspended', 'suspended_at' => now(), 'suspension_reason' => 'Spam.'])->save();

        $this->report($conversation->sender, $message, ['reason' => 'spam'])->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertSame(0, MessageReport::query()->count());
    }

    public function test_a_message_of_another_conversation_a_deleted_message_and_a_non_numeric_id_are_404(): void
    {
        [$conversation, $message] = $this->sellerMessage();
        [, $elsewhere] = $this->sellerMessage();
        $buyer = $conversation->sender;

        $this->actingAs($buyer)->post(route('conversations.messages.report', [$conversation, $elsewhere]), ['reason' => 'spam'])->assertNotFound();
        $this->actingAs($buyer)->post('/conversations/'.$conversation->id.'/messages/abc/report', ['reason' => 'spam'])->assertNotFound();
        $message->delete();
        $this->actingAs($buyer)->post(route('conversations.messages.report', [$conversation, $message->id]), ['reason' => 'spam'])->assertNotFound();

        $this->assertSame(0, MessageReport::query()->count());
    }

    public function test_a_message_deleted_after_binding_is_not_reported(): void
    {
        [$conversation, $message] = $this->sellerMessage();
        Message::query()->whereKey($message->id)->delete();

        $this->expectException(ModelNotFoundException::class);

        try {
            app(MessageReportService::class)->report($message, $conversation->sender, ReportReason::Spam, null);
        } finally {
            $this->assertSame(0, MessageReport::query()->count());
        }
    }

    public function test_the_other_partys_bubble_offers_the_report_dialog_and_own_bubbles_do_not(): void
    {
        [$conversation, $message] = $this->sellerMessage();
        $own = Message::factory()->for($conversation)->create();

        $html = $this->show($conversation, $conversation->sender);
        $bubble = $this->bubble($html, $message);

        $this->assertStringContainsString('<p id="message-'.$message->id.'-text" class="wrap-break-word">', $bubble);
        $this->assertStringContainsString('aria-describedby="message-'.$message->id.'-text"', $bubble);
        $this->assertStringContainsString('data-dialog-open="report-message-'.$message->id.'" commandfor="report-message-'.$message->id.'" command="show-modal"', $bubble);
        $this->assertStringContainsString('<dialog id="report-message-'.$message->id.'"', $bubble);
        $this->assertStringContainsString('Report this message?', $bubble);
        $this->assertStringContainsString('action="'.route('conversations.messages.report', [$conversation, $message]).'"', $bubble);
        $this->assertStringContainsString('id="report-message-'.$message->id.'-reason"', $bubble);
        $this->assertStringContainsString('id="report-message-'.$message->id.'-note"', $bubble);
        $this->assertStringContainsString('maxlength="500"', $bubble);
        $this->assertStringContainsString('Optional. Max 500 characters.', $bubble);
        $this->assertStringContainsString('The message stays visible until an admin reviews it.', $bubble);
        $this->assertSeeInOrderHtml($bubble, ['Choose a reason', 'Spam', 'Abusive', 'Scam', 'Other', 'Send report']);
        $this->assertStringNotContainsString('report-message', $this->bubble($html, $own));
    }

    public function test_after_reporting_the_bubble_shows_reported_in_place_of_the_control(): void
    {
        [$conversation, $message] = $this->sellerMessage();
        $unreported = Message::factory()->for($conversation)->create(['sender_id' => $conversation->receiver_id]);
        MessageReport::factory()->resolved()->create(['message_id' => $message->id]);

        $html = $this->show($conversation, $conversation->sender);

        $this->assertMatchesRegularExpression('/<span class="inline-flex items-center gap-1 text-muted"><svg[^>]*>.*?<\/svg>\s*Reported<\/span>/s', $this->bubble($html, $message));
        $this->assertStringNotContainsString('report-message-'.$message->id, $html);
        $this->assertStringContainsString('report-message-'.$unreported->id, $html);
    }

    public function test_another_viewers_report_does_not_mark_the_bubble_reported(): void
    {
        [$conversation, $message] = $this->sellerMessage();
        MessageReport::factory()->create(['message_id' => $message->id, 'reporter_id' => User::factory()->create()->id]);

        $html = $this->show($conversation, $conversation->sender);

        $this->assertStringContainsString('report-message-'.$message->id, $this->bubble($html, $message));
        $this->assertDoesNotMatchRegularExpression('/>\s*Reported<\/span>/', $html);
    }

    public function test_the_reported_user_and_an_admin_see_no_report_control_and_no_trace(): void
    {
        [$conversation, $message] = $this->sellerMessage();
        MessageReport::factory()->create(['message_id' => $message->id]);

        foreach ([$conversation->receiver, User::factory()->admin()->create()] as $viewer) {
            $html = $this->show($conversation, $viewer);

            $this->assertStringNotContainsString('report-message-'.$message->id, $html);
            $this->assertDoesNotMatchRegularExpression('/>\s*Reported<\/span>/', $html);
        }
    }

    public function test_every_bubbles_dialog_and_field_ids_are_unique(): void
    {
        [$conversation] = $this->sellerMessage();
        Message::factory()->count(2)->for($conversation)->create(['sender_id' => $conversation->receiver_id]);

        $html = $this->show($conversation, $conversation->sender);
        preg_match_all('/id="(report-message-[^"]+)"/', $html, $matches);

        $this->assertCount(20, $matches[1], 'Three bubbles and the live template, five ids each.');
        $this->assertSame($matches[1], array_unique($matches[1]));
    }

    /**
     * @return array{Conversation, Message}
     */
    private function sellerMessage(string $text = 'Pay me outside the app.'): array
    {
        $conversation = Conversation::factory()->for(CarListing::factory()->approved(), 'listing')->create();
        $message = Message::factory()->for($conversation)->create(['sender_id' => $conversation->receiver_id, 'message' => $text]);

        return [$conversation, $message];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function report(User $reporter, Message $message, array $payload): TestResponse
    {
        $response = $this->actingAs($reporter)->post(route('conversations.messages.report', [$message->conversation_id, $message]), $payload);
        $this->app['auth']->forgetGuards();

        return $response;
    }

    private function show(Conversation $conversation, User $viewer): string
    {
        $response = $this->actingAs($viewer)->get(route('conversations.show', $conversation))->assertOk();
        $this->app['auth']->forgetGuards();

        return $response->getContent();
    }

    private function bubble(string $html, Message $message): string
    {
        $start = strpos($html, '<li id="message-'.$message->id.'"');
        $this->assertNotFalse($start);

        return substr($html, $start, strpos($html, '</li>', $start) - $start);
    }

    /**
     * @return array<string, mixed>
     */
    private function reportRow(): array
    {
        return (array) DB::table('message_reports')->sole([
            'conversation_id', 'message_id', 'message_text', 'reporter_id', 'reported_user_id',
            'reason', 'note', 'status', 'outcome', 'resolved_by', 'resolved_at',
        ]);
    }

    /**
     * @param  list<string>  $needles
     */
    private function assertSeeInOrderHtml(string $html, array $needles): void
    {
        $position = 0;

        foreach ($needles as $needle) {
            $found = strpos($html, $needle, $position);
            $this->assertNotFalse($found, "Missing or out of order: {$needle}");
            $position = $found + strlen($needle);
        }
    }
}
