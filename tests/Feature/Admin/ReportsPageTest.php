<?php

namespace Tests\Feature\Admin;

use App\Enums\ReportOutcome;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\MessageReport;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ReportsPageTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_the_page_renders_the_spanish_kicker_title_and_the_open_and_resolved_chips(): void
    {
        $html = $this->page()->assertViewIs('pages.admin.reports.index')->getContent();

        $this->assertMatchesRegularExpression('/lang="es"[^>]*>\s*Denuncias/', $html);
        $this->assertStringContainsString('<h1 class="text-[38px] leading-none md:text-[56px]">Message reports</h1>', $html);
        $this->assertMatchesRegularExpression('/<nav data-brand-chips aria-label="Filter by status"/', $html);
        $this->assertMatchesRegularExpression('/<a href="'.preg_quote(route('admin.reports'), '/').'"\s*aria-current="true"[^>]*><svg[^>]*>.*?<\/svg>\s*Open<\/a>/s', $html);
        $this->assertMatchesRegularExpression('/<a href="'.preg_quote(route('admin.reports', ['status' => 'resolved']), '/').'"\s*class="[^"]*border-rule[^"]*">Resolved<\/a>/', $html);
    }

    public function test_open_lists_only_open_reports_oldest_first_with_ties_by_id(): void
    {
        $this->travelTo('2026-10-15 10:00:00');
        $newest = $this->report(['created_at' => now()]);
        $tiedFirst = $this->report(['created_at' => now()->subHour()]);
        $tiedSecond = $this->report(['created_at' => now()->subHour()]);
        $oldest = $this->report(['created_at' => now()->subDays(2)]);
        $this->resolvedReport(now()->subDays(5));

        $this->assertSame([$oldest->id, $tiedFirst->id, $tiedSecond->id, $newest->id], $this->ids());
        $this->assertSame($this->ids(), $this->ids(['status' => 'open']));
    }

    public function test_resolved_lists_only_resolved_reports_newest_resolution_first_with_ties_by_id_descending(): void
    {
        $this->travelTo('2026-10-15 10:00:00');
        $older = $this->resolvedReport(now()->subDays(3));
        $tiedFirst = $this->resolvedReport(now()->subDay());
        $tiedSecond = $this->resolvedReport(now()->subDay());
        $newest = $this->resolvedReport(now());
        $this->report();

        $this->assertSame([$newest->id, $tiedSecond->id, $tiedFirst->id, $older->id], $this->ids(['status' => 'resolved']));
    }

    public function test_twelve_reports_fit_a_page_and_the_links_keep_the_status(): void
    {
        foreach (range(1, 13) as $index) {
            $this->resolvedReport(now()->subMinutes($index));
        }

        $response = $this->page(['status' => 'resolved']);

        $this->assertCount(12, $response->viewData('reports'));
        $response->assertSee(e(route('admin.reports', ['status' => 'resolved', 'page' => 2])), false);
        $this->assertCount(1, $this->page(['status' => 'resolved', 'page' => 2])->viewData('reports'));
    }

    public function test_an_unknown_status_returns_to_the_bare_page_with_the_error(): void
    {
        $this->actingAs(User::factory()->admin()->create())->get(route('admin.reports', ['status' => 'archived']))
            ->assertRedirect(route('admin.reports'))
            ->assertSessionHasErrors('status');
    }

    public function test_each_view_has_its_empty_state_and_a_page_past_the_last_keeps_the_pagination(): void
    {
        $this->page()->assertSee('No open reports.')->assertDontSee('No resolved reports yet.');
        $this->page(['status' => 'resolved'])->assertSee('No resolved reports yet.');

        foreach (range(1, 13) as $index) {
            $this->report();
        }

        $this->page(['page' => 5])
            ->assertSee('No open reports.')
            ->assertSee(e(route('admin.reports', ['page' => 1])), false);
    }

    public function test_an_open_row_shows_the_snapshot_link_reason_note_users_time_and_stamp(): void
    {
        $this->travelTo('2026-10-15 09:05:00');
        $author = User::factory()->create(['username' => 'rafael']);
        $report = $this->reportAgainst($author, 'Pay the deposit first.', [
            'reporter_id' => null,
            'reason' => 'scam',
            'note' => 'Asked for money twice.',
        ]);
        $report->reporter->forceFill(['username' => 'lucia'])->save();

        $row = $this->row($this->page()->getContent(), $report);

        $this->assertStringContainsString('<span class="text-ink italic wrap-break-word">Pay the deposit first.</span>', $row);
        $this->assertStringContainsString('href="'.route('conversations.show', $report->conversation_id).'#message-'.$report->message_id.'"', $row);
        $this->assertStringContainsString('>Open conversation</a>', $row);
        $this->assertStringContainsString('<span>Scam</span>', $row);
        $this->assertStringContainsString('Asked for money twice.', $row);
        $this->assertSeeInOrderHtml($row, ['lucia', 'rafael', '<time datetime="2026-10-15T09:05:00+00:00">15.10.2026 09:05</time>', 'Open']);
        $this->assertStringContainsString('border-status-pending', $row);
        $this->assertStringNotContainsString('No note', $row);
        $this->assertStringNotContainsString('Message deleted', $row);
    }

    public function test_a_row_without_a_note_says_so_and_the_table_is_named(): void
    {
        $report = $this->report();
        $html = $this->page()->getContent();

        $this->assertStringContainsString('<span class="text-xs text-muted">No note</span>', $this->row($html, $report));
        $this->assertStringContainsString('<caption class="sr-only">Message reports</caption>', $html);
    }

    public function test_a_resolved_row_shows_the_outcome_the_resolver_and_the_time_and_offers_no_actions(): void
    {
        $this->travelTo('2026-10-15 10:00:00');
        $admin = User::factory()->admin()->create(['username' => 'moderadora']);
        $byAdmin = MessageReport::factory()->resolved(ReportOutcome::UserSuspended)->for($admin, 'resolver')->create(['resolved_at' => now()->subMinutes(30)]);
        $byRemoved = $this->resolvedReport(now()->subHour());

        $html = $this->page(['status' => 'resolved'])->getContent();
        $row = $this->row($html, $byAdmin);

        $this->assertStringContainsString('border-gold', $row);
        $this->assertStringContainsString('Resolved', $row);
        $this->assertStringContainsString('User suspended · by moderadora', $row);
        $this->assertStringContainsString('15.10.2026 09:30', $row);
        $this->assertStringContainsString('Dismissed · by a removed admin', $this->row($html, $byRemoved));
        $this->assertStringNotContainsString('<form', $row);
        $this->assertStringNotContainsString('data-dialog-open', $row);
    }

    public function test_a_row_whose_message_was_deleted_marks_it_keeps_the_snapshot_and_offers_no_delete(): void
    {
        $report = $this->report();
        $text = $report->message_text;
        Message::query()->whereKey($report->message_id)->delete();

        $row = $this->row($this->page()->getContent(), $report);

        $this->assertStringContainsString('<span class="text-[11px] tracking-[0.12em] text-ink-2 uppercase">Message deleted</span>', $row);
        $this->assertStringContainsString(e($text), $row);
        $this->assertStringContainsString('href="'.route('conversations.show', $report->conversation_id).'"', $row);
        $this->assertStringNotContainsString('#message-', $row);
        $this->assertStringNotContainsString('delete-reported-message-', $row);
        $this->assertStringContainsString('>Dismiss<', $row);
        $this->assertStringContainsString('suspend-report-'.$report->id, $row);
    }

    public function test_a_row_whose_reported_user_is_suspended_shows_the_status_and_offers_no_suspend(): void
    {
        $author = User::factory()->suspended(now()->setDate(2026, 11, 14))->create();
        $report = $this->reportAgainst($author);

        $row = $this->row($this->page()->getContent(), $report);

        $this->assertStringContainsString('<span class="text-xs text-red-deep">Suspended until 14.11.2026</span>', $row);
        $this->assertStringNotContainsString('suspend-report-', $row);
        $this->assertStringContainsString('>Dismiss<', $row);
        $this->assertStringContainsString('delete-reported-message-'.$report->id, $row);
    }

    public function test_an_open_row_offers_the_three_actions_each_naming_the_user_and_the_time_and_carrying_the_view(): void
    {
        $this->travelTo('2026-10-15 09:05:00');
        $report = $this->reportAgainst(User::factory()->create(['username' => 'rafael']));
        foreach (range(1, 12) as $index) {
            $this->report(['created_at' => now()->subDays(2)]);
        }
        $row = $this->row($this->page(['status' => 'open', 'page' => 2])->getContent(), $report);
        $action = e(route('admin.reports.resolve', [$report, 'status' => 'open', 'page' => 2]));
        $context = '<span class="sr-only"> the report on rafael, 15.10.2026 09:05</span>';

        $this->assertSame(3, substr_count($row, 'action="'.$action.'"'));
        $this->assertStringContainsString('<input type="hidden" name="outcome" value="dismissed">', $row);
        $this->assertStringContainsString('Dismiss'.$context, $row);
        $this->assertStringContainsString('data-dialog-open="delete-reported-message-'.$report->id.'"', $row);
        $this->assertStringContainsString('Delete message'.$context, $row);
        $this->assertStringContainsString('<dialog id="delete-reported-message-'.$report->id.'"', $row);
        $this->assertStringContainsString('Delete the message by rafael?', $row);
        $this->assertStringContainsString('It disappears for both participants; the report keeps a copy.', $row);
        $this->assertStringContainsString('<input type="hidden" name="outcome" value="message_deleted">', $row);
        $this->assertStringContainsString('data-dialog-open="suspend-report-'.$report->id.'"', $row);
        $this->assertStringContainsString('Suspend user'.$context, $row);
        $this->assertStringContainsString('<dialog id="suspend-report-'.$report->id.'"', $row);
        $this->assertStringContainsString('Suspend rafael?', $row);
        $this->assertStringContainsString('id="suspend-report-'.$report->id.'-reason"', $row);
        $this->assertStringContainsString('id="suspend-report-'.$report->id.'-duration"', $row);
        $this->assertStringContainsString('<input type="hidden" name="outcome" value="user_suspended">', $row);
    }

    public function test_dialog_and_field_ids_are_unique_across_rows(): void
    {
        $this->report();
        $this->report();

        preg_match_all('/id="((?:delete-reported-message|suspend-report)-[^"]+)"/', $this->page()->getContent(), $matches);

        $this->assertNotEmpty($matches[1]);
        $this->assertSame($matches[1], array_values(array_unique($matches[1])));
    }

    public function test_message_text_and_note_are_escaped(): void
    {
        $report = $this->report(['note' => '<b>loud</b>']);
        DB::table('message_reports')->where('id', $report->id)->update(['message_text' => '<script>alert(1)</script>']);

        $html = $this->page()->getContent();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        $this->assertStringContainsString('&lt;b&gt;loud&lt;/b&gt;', $html);
    }

    public function test_the_reports_nav_item_is_current_and_sits_between_listings_and_conversations(): void
    {
        $html = $this->page()->getContent();

        $this->assertMatchesRegularExpression('/<a href="'.preg_quote(route('admin.reports'), '/').'"\s+aria-current="page"\s+class="[^"]*">\s*Reports\s*<\/a>/', $html);
        $this->assertSeeInOrderHtml($html, ['href="'.route('admin.users').'"', 'href="'.route('admin.listings').'"', 'href="'.route('admin.reports').'"', 'href="'.route('conversations.index').'"']);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function views(): array
    {
        return [
            'open' => ['open'],
            'resolved' => ['resolved'],
        ];
    }

    #[DataProvider('views')]
    public function test_the_query_count_does_not_grow_with_the_rows(string $status): void
    {
        $admin = User::factory()->admin()->create();
        $this->mixedRows(2, $status, $admin);
        $few = $this->queriesFor($admin, $status);

        $this->mixedRows(10, $status, $admin);
        $many = $this->queriesFor($admin, $status);

        $this->assertCount(12, $this->page(['status' => $status], $admin)->viewData('reports'));
        $this->assertSame($few, $many);
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function page(array $query = [], ?User $admin = null): TestResponse
    {
        $response = $this->actingAs($admin ?? User::factory()->admin()->create())->get(route('admin.reports', $query));
        $this->app['auth']->forgetGuards();

        return $response->assertOk();
    }

    /**
     * @param  array<string, mixed>  $query
     * @return list<int>
     */
    private function ids(array $query = []): array
    {
        return $this->page($query)->viewData('reports')->pluck('id')->all();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function report(array $attributes = []): MessageReport
    {
        return MessageReport::factory()->create($attributes);
    }

    private function resolvedReport(mixed $resolvedAt): MessageReport
    {
        return MessageReport::factory()->resolved()->create(['resolved_at' => $resolvedAt]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function reportAgainst(User $author, string $text = 'Pay me outside the app.', array $attributes = []): MessageReport
    {
        $conversation = Conversation::factory()->create(['receiver_id' => $author->id]);
        $message = Message::factory()->for($conversation)->create(['sender_id' => $author->id, 'message' => $text]);

        return MessageReport::factory()->create(array_filter(['message_id' => $message->id, ...$attributes], fn (mixed $value): bool => $value !== null));
    }

    private function mixedRows(int $count, string $status, User $admin): void
    {
        foreach (range(1, $count) as $index) {
            $report = $this->reportAgainst($index % 2 === 0 ? User::factory()->suspended()->create() : User::factory()->create(), attributes: ['note' => 'A note.']);

            if ($index % 3 === 0) {
                Message::query()->whereKey($report->message_id)->delete();
            }

            if ($status === 'resolved') {
                $report->forceFill(['status' => 'resolved', 'outcome' => 'dismissed', 'resolved_at' => now(), 'resolved_by' => $index % 2 === 0 ? $admin->id : null])->save();
            }
        }
    }

    private function queriesFor(User $admin, string $status): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->page(['status' => $status], $admin);
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    private function row(string $html, MessageReport $report): string
    {
        preg_match_all('/<tr class="block border-t.*?<\/tr>/s', $html, $rows);
        $report->refresh();
        $needles = $report->isOpen()
            ? ['/admin/reports/'.$report->id.'/resolve"', '/admin/reports/'.$report->id.'/resolve?']
            : [e($report->message_text)];
        $matches = array_values(array_filter($rows[0], fn (string $row): bool => str_contains($row, $needles[0]) || str_contains($row, $needles[1] ?? $needles[0])));

        $this->assertCount(1, $matches, "Row of report {$report->id} not found once.");

        return $matches[0];
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
