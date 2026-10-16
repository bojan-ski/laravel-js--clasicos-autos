<?php

namespace Tests\Feature\Consolidation\StageF;

use App\Enums\ReportOutcome;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\MessageReport;
use App\Models\User;
use Generator;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ReportLifecycleMatrixTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const string NOW = '2026-10-16 10:00:00';

    private const string PAST = '2026-10-13 10:00:00';

    private const string REASON = 'Asked several buyers for deposits outside the app.';

    private const string OLD_REASON = 'An earlier warning about spam.';

    private User $admin;

    private User $previousAdmin;

    private User $author;

    private User $reporter;

    private Conversation $conversation;

    private Message $message;

    private MessageReport $report;

    private MessageReport $sibling;

    private MessageReport $unrelated;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->travelTo(self::NOW);

        $this->admin = User::factory()->admin()->create();
        $this->previousAdmin = User::factory()->admin()->create();
    }

    public function test_a_participant_reports_the_other_partys_message_and_the_report_opens_with_a_snapshot(): void
    {
        $this->conversationAndMessage();

        $this->actingAs($this->reporter)
            ->post(route('conversations.messages.report', [$this->conversation, $this->message]), ['reason' => 'scam', 'note' => 'Asked for a deposit.'])
            ->assertRedirect(route('conversations.show', $this->conversation).'#message-'.$this->message->id)
            ->assertSessionHas('success', __('app.conversations.report.flash.sent'));

        $this->assertSame(
            [
                'conversation_id' => $this->conversation->id,
                'message_id' => $this->message->id,
                'message_text' => $this->message->message,
                'reporter_id' => $this->reporter->id,
                'reported_user_id' => $this->author->id,
                'reason' => 'scam',
                'note' => 'Asked for a deposit.',
                'status' => 'open',
                'outcome' => null,
                'resolved_by' => null,
                'resolved_at' => null,
            ],
            (array) DB::table('message_reports')->first(['conversation_id', 'message_id', 'message_text', 'reporter_id', 'reported_user_id', 'reason', 'note', 'status', 'outcome', 'resolved_by', 'resolved_at']),
        );
    }

    /**
     * @return Generator<string, array{string, string, string}>
     */
    public static function cells(): Generator
    {
        $open = [
            're_report' => 'DUPLICATE',
            'dismiss' => 'R',
            'delete_message' => 'R',
            'delete_message_after_sender_delete' => 'G',
            'suspend_author' => 'R',
            'suspend_author_already_suspended' => 'G',
            'sender_delete' => 'KEEP',
        ];
        $resolved = [
            're_report' => 'DUPLICATE',
            'dismiss' => 'G',
            'delete_message' => 'G',
            'delete_message_after_sender_delete' => 'G',
            'suspend_author' => 'G',
            'suspend_author_already_suspended' => 'G',
            'sender_delete' => 'KEEP',
        ];
        $states = [
            'open' => $open,
            'resolved dismissed' => $resolved,
            'resolved message_deleted' => [...$resolved, 're_report' => 'NOT_FOUND', 'sender_delete' => 'NOT_FOUND'],
            'resolved user_suspended' => $resolved,
        ];

        foreach ($states as $from => $byAction) {
            foreach ($byAction as $action => $cell) {
                yield "{$action} from {$from}" => [$action, $from, $cell];
            }
        }
    }

    #[DataProvider('cells')]
    public function test_each_action_from_each_state(string $action, string $from, string $cell): void
    {
        $this->reportIn($from);

        if ($action === 'suspend_author_already_suspended') {
            DB::table('users')->where('id', $this->author->id)->update([
                'status' => 'suspended',
                'suspended_at' => self::PAST,
                'suspended_until' => '2026-10-20 10:00:00',
                'suspension_reason' => self::OLD_REASON,
            ]);
        }

        if ($action === 'delete_message_after_sender_delete' && $this->messageExists()) {
            $this->actingAs($this->author)->delete(route('conversations.messages.destroy', [$this->conversation, $this->message]))->assertRedirect();
            $this->app['auth']->forgetGuards();
        }

        $before = $this->snapshot();
        $response = $this->perform($action);

        match ($cell) {
            'R' => $this->assertResolvedCell($response, $action, $before),
            'G' => $this->assertGuardCell($response, $before),
            'DUPLICATE' => $this->assertDuplicateCell($response, $before),
            'KEEP' => $this->assertKeptOnSenderDeleteCell($response, $before),
            'NOT_FOUND' => $this->assertNotFoundCell($response, $before),
        };
    }

    /**
     * @return array<string, array{string}>
     */
    public static function refusedActors(): array
    {
        return [
            'a guest reports' => ['guest reports'],
            'the sender reports their own message' => ['sender reports'],
            'an admin reports' => ['admin reports'],
            'an app user outside the conversation reports' => ['outsider reports'],
            'a suspended participant reports' => ['suspended reports'],
            'a guest resolves' => ['guest resolves'],
            'an app user resolves' => ['app user resolves'],
            'a suspended app user resolves' => ['suspended resolves'],
        ];
    }

    #[DataProvider('refusedActors')]
    public function test_a_refused_actor_changes_no_row(string $case): void
    {
        $this->reportIn('open');
        $other = Message::factory()->for($this->conversation)->create(['sender_id' => $this->author->id]);
        $before = $this->snapshot();
        $reports = MessageReport::query()->count();

        $actor = match ($case) {
            'guest reports', 'guest resolves' => null,
            'sender reports' => $this->author,
            'admin reports' => $this->admin,
            'outsider reports', 'app user resolves' => User::factory()->create(),
            'suspended reports' => $this->suspendedReporter(),
            'suspended resolves' => User::factory()->suspended(now()->addDays(3))->create(),
        };

        if ($actor !== null) {
            $this->actingAs($actor);
        }

        $response = str_ends_with($case, 'resolves')
            ? $this->patch(route('admin.reports.resolve', $this->report), ['outcome' => 'dismissed'])
            : $this->post(route('conversations.messages.report', [$this->conversation, $other]), ['reason' => 'spam']);

        match ($case) {
            'guest reports', 'guest resolves', 'suspended reports', 'suspended resolves' => $response->assertRedirect(route('login')),
            'outsider reports' => $response->assertNotFound()->assertSee(__('app.errors.404.title')),
            default => $response->assertForbidden()->assertSee(__('app.errors.403.title')),
        };

        $this->assertSame($before, $this->snapshot());
        $this->assertSame($reports, MessageReport::query()->count());
    }

    private function conversationAndMessage(): void
    {
        $this->reporter = User::factory()->create();
        $this->conversation = Conversation::factory()->for($this->reporter, 'sender')->create();
        $this->author = $this->conversation->receiver;
        $this->message = Message::factory()->for($this->conversation)->create(['sender_id' => $this->author->id]);
    }

    private function reportIn(string $from): void
    {
        $this->conversationAndMessage();
        $this->report = MessageReport::factory()->create(['message_id' => $this->message->id]);
        $this->assertSame([$this->author->id, $this->reporter->id], [$this->report->reported_user_id, $this->report->reporter_id]);

        if ($from !== 'open') {
            $outcome = ReportOutcome::from(substr($from, strlen('resolved ')));
            $this->report->forceFill(['status' => 'resolved', 'outcome' => $outcome, 'resolved_by' => $this->previousAdmin->id, 'resolved_at' => self::PAST])->save();

            if ($outcome === ReportOutcome::MessageDeleted) {
                $this->message->delete();
            }
        }

        $this->sibling = MessageReport::factory()->create([
            'message_id' => Message::factory()->for($this->conversation)->create(['sender_id' => $this->author->id])->id,
        ]);
        $this->unrelated = MessageReport::factory()->create();
    }

    private function perform(string $action): TestResponse
    {
        $resolve = fn (array $payload): TestResponse => $this->actingAs($this->admin)->patch(route('admin.reports.resolve', $this->report), $payload);

        return match ($action) {
            're_report' => $this->actingAs($this->reporter)->post(route('conversations.messages.report', [$this->conversation, $this->message->id]), ['reason' => 'abusive']),
            'dismiss' => $resolve(['outcome' => 'dismissed']),
            'delete_message', 'delete_message_after_sender_delete' => $resolve(['outcome' => 'message_deleted']),
            'suspend_author', 'suspend_author_already_suspended' => $resolve(['outcome' => 'user_suspended', 'reason' => self::REASON, 'duration' => '7_days']),
            'sender_delete' => $this->actingAs($this->author)->delete(route('conversations.messages.destroy', [$this->conversation, $this->message->id])),
        };
    }

    /**
     * @param  array<string, mixed>  $before
     */
    private function assertResolvedCell(TestResponse $response, string $action, array $before): void
    {
        $outcome = ['dismiss' => ReportOutcome::Dismissed, 'delete_message' => ReportOutcome::MessageDeleted, 'suspend_author' => ReportOutcome::UserSuspended][$action];
        $response->assertRedirect(route('admin.reports'))->assertSessionHas('success', __("app.admin.reports.flash.{$outcome->value}"));
        $after = $this->snapshot();
        $deleted = $outcome === ReportOutcome::MessageDeleted;

        $this->assertSame(['resolved', $outcome->value, $this->admin->id, self::NOW, $deleted ? null : $this->message->id, $this->message->message], $after['report']);
        $this->assertSame(! $deleted, $after['message']);
        $this->assertSame($before['unrelated'], $after['unrelated']);

        if ($outcome === ReportOutcome::UserSuspended) {
            $this->assertSame(['suspended', self::NOW, '2026-10-23 10:00:00', self::REASON], $after['author']);
            $this->assertSame(['resolved', 'user_suspended', $this->admin->id, self::NOW, $this->sibling->message_id, $this->sibling->message_text], $after['sibling']);

            return;
        }

        $this->assertSame($before['author'], $after['author']);
        $this->assertSame($before['sibling'], $after['sibling']);
    }

    /**
     * @param  array<string, mixed>  $before
     */
    private function assertGuardCell(TestResponse $response, array $before): void
    {
        $response->assertRedirect(route('admin.reports'))->assertSessionHas('error', __('app.admin.reports.flash.error'));

        $this->assertSame($before, $this->snapshot());
    }

    /**
     * @param  array<string, mixed>  $before
     */
    private function assertDuplicateCell(TestResponse $response, array $before): void
    {
        $response->assertRedirect(route('conversations.show', $this->conversation).'#message-'.$this->message->id)
            ->assertSessionHas('error', __('app.conversations.report.flash.already_reported'));

        $this->assertSame($before, $this->snapshot());
        $this->assertSame(1, MessageReport::query()->where('message_id', $this->message->id)->where('reporter_id', $this->reporter->id)->count());
    }

    /**
     * @param  array<string, mixed>  $before
     */
    private function assertKeptOnSenderDeleteCell(TestResponse $response, array $before): void
    {
        $response->assertRedirect(route('conversations.show', $this->conversation))->assertSessionHas('success', __('app.conversations.deleted'));
        $after = $this->snapshot();

        $this->assertFalse($after['message']);
        $this->assertSame([...array_slice($before['report'], 0, 4), null, $this->message->message], $after['report']);
        $this->assertSame([...$before, 'report' => $after['report'], 'message' => false], $after);
    }

    /**
     * @param  array<string, mixed>  $before
     */
    private function assertNotFoundCell(TestResponse $response, array $before): void
    {
        $response->assertNotFound();

        $this->assertSame($before, $this->snapshot());
    }

    private function suspendedReporter(): User
    {
        DB::table('users')->where('id', $this->reporter->id)->update(['status' => 'suspended', 'suspended_at' => self::PAST, 'suspension_reason' => self::OLD_REASON]);

        return $this->reporter->fresh();
    }

    private function messageExists(): bool
    {
        return Message::query()->whereKey($this->message->id)->exists();
    }

    /**
     * @return array{report: list<mixed>, sibling: list<mixed>, unrelated: list<mixed>, author: list<?string>, message: bool}
     */
    private function snapshot(): array
    {
        $report = fn (MessageReport $report): array => array_values((array) DB::table('message_reports')
            ->where('id', $report->id)
            ->first(['status', 'outcome', 'resolved_by', 'resolved_at', 'message_id', 'message_text']));

        return [
            'report' => $report($this->report),
            'sibling' => $report($this->sibling),
            'unrelated' => $report($this->unrelated),
            'author' => array_values((array) DB::table('users')->where('id', $this->author->id)->first(['status', 'suspended_at', 'suspended_until', 'suspension_reason'])),
            'message' => $this->messageExists(),
        ];
    }
}
