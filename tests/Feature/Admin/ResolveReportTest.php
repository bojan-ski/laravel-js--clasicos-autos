<?php

namespace Tests\Feature\Admin;

use App\Enums\UserStatus;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\MessageReport;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ResolveReportTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const string REASON = 'Asked several buyers for deposits outside the app.';

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->admin = User::factory()->admin()->create();
    }

    public function test_dismiss_resolves_only_that_report_keeps_the_message_and_returns_to_the_view(): void
    {
        $this->travelTo('2026-10-15 10:00:00');
        $report = MessageReport::factory()->create();
        $otherOnSameUser = $this->reportAgainst($report->reportedUser);

        $this->resolve($report, ['outcome' => 'dismissed'], ['status' => 'open', 'page' => 2])
            ->assertRedirect(route('admin.reports', ['status' => 'open', 'page' => 2]))
            ->assertSessionHas('success', 'The report has been dismissed.');

        $this->assertSame(['resolved', 'dismissed', $this->admin->id, '2026-10-15 10:00:00'], $this->resolution($report));
        $this->assertModelExists(Message::query()->findOrFail($report->message_id));
        $this->assertSame(['open', null, null, null], $this->resolution($otherOnSameUser));
        $this->assertFalse($report->reportedUser->fresh()->isSuspended());
    }

    public function test_delete_message_removes_it_from_the_thread_and_the_report_keeps_the_snapshot(): void
    {
        $this->travelTo('2026-10-15 10:00:00');
        $report = MessageReport::factory()->create();
        $text = $report->message_text;
        $messageId = $report->message_id;

        $this->resolve($report, ['outcome' => 'message_deleted'])
            ->assertRedirect(route('admin.reports'))
            ->assertSessionHas('success', 'The message has been deleted. The report keeps a copy.');

        $this->assertSame(['resolved', 'message_deleted', $this->admin->id, '2026-10-15 10:00:00'], $this->resolution($report));
        $this->assertNull(Message::query()->find($messageId));
        $this->assertSame([null, $text], array_values((array) DB::table('message_reports')->where('id', $report->id)->first(['message_id', 'message_text'])));
        $this->actingAs($report->reporter)->get(route('conversations.show', $report->conversation_id))
            ->assertOk()
            ->assertDontSee('id="message-'.$messageId.'"', false);
    }

    public function test_suspend_user_suspends_the_author_and_resolves_every_open_report_on_them_only(): void
    {
        $this->travelTo('2026-10-15 10:00:00');
        $report = MessageReport::factory()->create();
        $author = $report->reportedUser;
        $otherOpen = $this->reportAgainst($author);
        $alreadyDismissed = MessageReport::factory()->resolved()->create(['message_id' => $this->messageBy($author)->id]);
        $otherUsers = MessageReport::factory()->create();
        $messages = Message::query()->where('sender_id', $author->id)->count();

        $this->resolve($report, ['outcome' => 'user_suspended', 'reason' => self::REASON, 'duration' => '30_days'], ['page' => 3])
            ->assertRedirect(route('admin.reports', ['page' => 3]))
            ->assertSessionHas('success', 'The user has been suspended and their open reports are resolved.');

        $author->refresh();
        $this->assertSame(UserStatus::Suspended, $author->status);
        $this->assertSame(self::REASON, $author->suspension_reason);
        $this->assertSame('2026-11-14 10:00:00', $author->suspended_until->toDateTimeString());
        foreach ([$report, $otherOpen] as $closed) {
            $this->assertSame(['resolved', 'user_suspended', $this->admin->id, '2026-10-15 10:00:00'], $this->resolution($closed));
        }
        $this->assertSame(['resolved', 'dismissed', null], array_slice($this->resolution($alreadyDismissed), 0, 3));
        $this->assertSame(['open', null, null, null], $this->resolution($otherUsers));
        $this->assertSame($messages, Message::query()->where('sender_id', $author->id)->count());
    }

    public function test_an_indefinite_suspension_from_a_report_has_no_end(): void
    {
        $report = MessageReport::factory()->create();

        $this->resolve($report, ['outcome' => 'user_suspended', 'reason' => self::REASON, 'duration' => 'indefinite'])->assertSessionHas('success');

        $this->assertTrue($report->reportedUser->fresh()->isSuspended());
        $this->assertNull($report->reportedUser->fresh()->suspended_until);
    }

    /**
     * @return array<string, array{array<string, string>}>
     */
    public static function outcomes(): array
    {
        return [
            'dismiss' => [['outcome' => 'dismissed']],
            'delete message' => [['outcome' => 'message_deleted']],
            'suspend user' => [['outcome' => 'user_suspended', 'reason' => self::REASON, 'duration' => '7_days']],
        ];
    }

    /**
     * @param  array<string, string>  $payload
     */
    #[DataProvider('outcomes')]
    public function test_a_report_resolved_meanwhile_is_never_resolved_again(array $payload): void
    {
        $report = MessageReport::factory()->resolved()->create(['resolved_at' => '2026-10-01 08:00:00']);
        $before = $this->resolution($report);

        $this->resolve($report, $payload)
            ->assertRedirect(route('admin.reports'))
            ->assertSessionHas('error', 'This report can no longer be resolved that way.');

        $this->assertSame($before, $this->resolution($report));
        $this->assertNotNull(Message::query()->find($report->message_id));
        $this->assertFalse($report->reportedUser->fresh()->isSuspended());
    }

    public function test_delete_message_after_the_sender_deleted_it_is_refused_and_the_report_stays_open(): void
    {
        $report = MessageReport::factory()->create();
        Message::query()->whereKey($report->message_id)->delete();

        $this->resolve($report, ['outcome' => 'message_deleted'])
            ->assertRedirect(route('admin.reports'))
            ->assertSessionHas('error', 'This report can no longer be resolved that way.');

        $this->assertSame(['open', null, null, null], $this->resolution($report));
    }

    public function test_suspend_user_on_an_already_suspended_author_is_refused_and_the_report_stays_open(): void
    {
        $report = MessageReport::factory()->create();
        DB::table('users')->where('id', $report->reported_user_id)->update(['status' => 'suspended', 'suspended_at' => now(), 'suspension_reason' => 'Earlier reason.']);

        $this->resolve($report, ['outcome' => 'user_suspended', 'reason' => self::REASON, 'duration' => '7_days'])
            ->assertRedirect(route('admin.reports'))
            ->assertSessionHas('error', 'This report can no longer be resolved that way.');

        $this->assertSame(['open', null, null, null], $this->resolution($report));
        $this->assertSame('Earlier reason.', $report->reportedUser->fresh()->suspension_reason);
    }

    public function test_hostile_view_values_and_a_hostile_referer_are_dropped(): void
    {
        $report = MessageReport::factory()->create();

        $response = $this->actingAs($this->admin)->call(
            'PATCH',
            route('admin.reports.resolve', [$report, 'status' => 'archived', 'page' => '-3']),
            ['outcome' => 'dismissed'],
            server: $this->transformHeadersToServerVars(['Referer' => 'https://evil.example/']),
        );

        $response->assertRedirect(route('admin.reports'))->assertSessionHas('success');
        $this->resolve(MessageReport::factory()->create(), ['outcome' => 'dismissed'], ['status' => 'resolved', 'page' => 'abc'])
            ->assertRedirect(route('admin.reports', ['status' => 'resolved']));
    }

    /**
     * @return array<string, array{array<string, string>, string}>
     */
    public static function invalidPayloads(): array
    {
        return [
            'no outcome' => [[], 'The outcome field is required.'],
            'unknown outcome' => [['outcome' => 'reopened'], 'The selected outcome is invalid.'],
            'suspend without a reason' => [['outcome' => 'user_suspended', 'duration' => '7_days'], 'The reason field is required.'],
            'suspend with an unknown duration' => [['outcome' => 'user_suspended', 'reason' => self::REASON, 'duration' => '1_year'], 'The selected duration is invalid.'],
            'suspend with a reason over 500' => [['outcome' => 'user_suspended', 'reason' => str_repeat('a', 501), 'duration' => '7_days'], 'The reason field must not be greater than 500 characters.'],
        ];
    }

    /**
     * @param  array<string, string>  $payload
     */
    #[DataProvider('invalidPayloads')]
    public function test_an_invalid_resolution_returns_to_the_view_with_the_first_error_and_changes_nothing(array $payload, string $error): void
    {
        $report = MessageReport::factory()->create();

        $this->resolve($report, $payload, ['status' => 'open'])
            ->assertRedirect(route('admin.reports', ['status' => 'open']))
            ->assertSessionHas('error', $error)
            ->assertSessionDoesntHaveErrors();

        $this->assertSame(['open', null, null, null], $this->resolution($report));
        $this->assertFalse($report->reportedUser->fresh()->isSuspended());
    }

    public function test_a_dismiss_ignores_suspension_fields_and_server_controlled_keys(): void
    {
        $report = MessageReport::factory()->create();
        $other = User::factory()->admin()->create();

        $this->resolve($report, ['outcome' => 'dismissed', 'reason' => '', 'duration' => 'bogus', 'resolved_by' => $other->id, 'status' => 'open'])
            ->assertSessionHas('success');

        $this->assertSame(['resolved', 'dismissed', $this->admin->id], array_slice($this->resolution($report), 0, 3));
        $this->assertFalse($report->reportedUser->fresh()->isSuspended());
    }

    public function test_suspending_from_a_report_obeys_the_user_suspend_policy_while_other_outcomes_do_not_need_it(): void
    {
        $report = MessageReport::factory()->create();
        DB::table('users')->where('id', $report->reported_user_id)->update(['role' => 'admin']);

        $this->resolve($report, ['outcome' => 'user_suspended', 'reason' => self::REASON, 'duration' => '7_days'])
            ->assertForbidden()
            ->assertSessionDoesntHaveErrors();
        $this->assertSame(['open', null, null, null], $this->resolution($report));

        $this->resolve($report, ['outcome' => 'dismissed'])->assertSessionHas('success');
    }

    public function test_an_app_user_is_refused_before_validation_and_a_guest_is_sent_to_login(): void
    {
        $report = MessageReport::factory()->create();

        $this->actingAs($report->reporter)
            ->patch(route('admin.reports.resolve', $report), ['outcome' => 'not-an-outcome'])
            ->assertForbidden()
            ->assertSessionDoesntHaveErrors()
            ->assertSessionMissing('error');
        $this->app['auth']->forgetGuards();
        $this->patch(route('admin.reports.resolve', $report), ['outcome' => 'dismissed'])->assertRedirect(route('login'));

        $this->assertSame(['open', null, null, null], $this->resolution($report));
    }

    public function test_an_unknown_or_non_numeric_report_is_404(): void
    {
        $this->actingAs($this->admin)->patch(route('admin.reports.resolve', 999), ['outcome' => 'dismissed'])->assertNotFound();
        $this->actingAs($this->admin)->patch('/admin/reports/abc/resolve', ['outcome' => 'dismissed'])->assertNotFound();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $view
     */
    private function resolve(MessageReport $report, array $payload, array $view = []): TestResponse
    {
        $response = $this->actingAs($this->admin)->patch(route('admin.reports.resolve', [$report, ...$view]), $payload);
        $this->app['auth']->forgetGuards();

        return $response;
    }

    private function messageBy(User $author): Message
    {
        $conversation = Conversation::factory()->create(['receiver_id' => $author->id]);

        return Message::factory()->for($conversation)->create(['sender_id' => $author->id]);
    }

    private function reportAgainst(User $author): MessageReport
    {
        return MessageReport::factory()->create(['message_id' => $this->messageBy($author)->id]);
    }

    /**
     * @return list<mixed>
     */
    private function resolution(MessageReport $report): array
    {
        return array_values((array) DB::table('message_reports')->where('id', $report->id)->first(['status', 'outcome', 'resolved_by', 'resolved_at']));
    }
}
