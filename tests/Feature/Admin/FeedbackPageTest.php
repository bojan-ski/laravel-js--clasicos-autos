<?php

namespace Tests\Feature\Admin;

use App\Models\ContactMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FeedbackPageTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_the_page_renders_the_spanish_kicker_title_and_the_new_and_handled_chips(): void
    {
        $html = $this->page()->assertViewIs('pages.admin.feedback.index')->getContent();

        $this->assertMatchesRegularExpression('/lang="es"[^>]*>\s*Buzón/', $html);
        $this->assertStringContainsString('<h1 class="text-[38px] leading-none md:text-[56px]">Feedback</h1>', $html);
        $this->assertMatchesRegularExpression('/<nav data-brand-chips aria-label="Filter by status"/', $html);
        $this->assertMatchesRegularExpression('/<a href="'.preg_quote(route('admin.feedback'), '/').'"\s*aria-current="true"[^>]*><svg[^>]*>.*?<\/svg>\s*New<\/a>/s', $html);
        $this->assertMatchesRegularExpression('/<a href="'.preg_quote(route('admin.feedback', ['status' => 'handled']), '/').'"\s*class="[^"]*border-rule[^"]*">Handled<\/a>/', $html);
    }

    public function test_new_lists_only_new_messages_newest_first_with_ties_by_id_descending(): void
    {
        $this->travelTo('2026-10-16 10:00:00');
        $oldest = ContactMessage::factory()->create(['created_at' => now()->subDays(2)]);
        $tiedFirst = ContactMessage::factory()->create(['created_at' => now()->subHour()]);
        $tiedSecond = ContactMessage::factory()->create(['created_at' => now()->subHour()]);
        $newest = ContactMessage::factory()->create(['created_at' => now()]);
        ContactMessage::factory()->handled()->create();

        $this->assertSame([$newest->id, $tiedSecond->id, $tiedFirst->id, $oldest->id], $this->ids());
        $this->assertSame($this->ids(), $this->ids(['status' => 'new']));
    }

    public function test_handled_lists_only_handled_messages_newest_handling_first_with_ties_by_id_descending(): void
    {
        $this->travelTo('2026-10-16 10:00:00');
        $older = ContactMessage::factory()->handled()->create(['handled_at' => now()->subDays(3), 'created_at' => now()->subMinute()]);
        $tiedFirst = ContactMessage::factory()->handled()->create(['handled_at' => now()->subDay()]);
        $tiedSecond = ContactMessage::factory()->handled()->create(['handled_at' => now()->subDay()]);
        $newest = ContactMessage::factory()->handled()->create(['handled_at' => now(), 'created_at' => now()->subDays(9)]);
        ContactMessage::factory()->create();

        $this->assertSame([$newest->id, $tiedSecond->id, $tiedFirst->id, $older->id], $this->ids(['status' => 'handled']));
    }

    public function test_twelve_messages_fit_a_page_and_the_links_keep_the_status(): void
    {
        ContactMessage::factory()->handled()->count(13)->create();

        $response = $this->page(['status' => 'handled']);

        $this->assertCount(12, $response->viewData('contactMessages'));
        $response->assertSee(e(route('admin.feedback', ['status' => 'handled', 'page' => 2])), false);
        $this->assertCount(1, $this->page(['status' => 'handled', 'page' => 2])->viewData('contactMessages'));
    }

    public function test_an_unknown_status_returns_to_the_bare_page_with_the_error(): void
    {
        $this->actingAs(User::factory()->admin()->create())->get(route('admin.feedback', ['status' => 'archived']))
            ->assertRedirect(route('admin.feedback'))
            ->assertSessionHasErrors('status');
    }

    public function test_each_view_has_its_empty_state_and_a_page_past_the_last_keeps_the_pagination(): void
    {
        $this->page()->assertSee('No new messages.')->assertDontSee('No handled messages yet.');
        $this->page(['status' => 'handled'])->assertSee('No handled messages yet.');

        ContactMessage::factory()->count(13)->create();

        $this->page(['page' => 5])
            ->assertSee('No new messages.')
            ->assertSee(e(route('admin.feedback', ['page' => 1])), false);
    }

    public function test_a_new_row_shows_the_sender_from_the_account_the_full_text_the_time_and_the_stamp(): void
    {
        $this->travelTo('2026-10-16 09:05:00');
        $sender = User::factory()->create(['username' => 'lucia', 'email' => 'lucia@example.com', 'phone_number' => '+5355500111']);
        $message = ContactMessage::factory()->for($sender)->create([
            'subject' => 'Approval time',
            'message' => "First line.\nSecond line.",
        ]);

        $html = $this->page()->getContent();
        $row = $this->row($html, $message);

        $this->assertStringContainsString('<span class="wrap-break-word">lucia</span>', $row);
        $this->assertStringContainsString('<a href="mailto:lucia@example.com" class="inline-flex min-h-11 items-center text-xs text-ink underline underline-offset-4 wrap-break-word hover:no-underline md:min-h-0">lucia@example.com</a>', $row);
        $this->assertStringContainsString('<span class="text-xs text-ink-2">+5355500111</span>', $row);
        $this->assertStringContainsString('<span class="min-w-0 font-heading text-ink italic wrap-break-word">Approval time</span>', $row);
        $this->assertStringContainsString('<span class="min-w-0 whitespace-pre-line wrap-break-word">First line.'."\n".'Second line.</span>', $row);
        $this->assertStringContainsString('<time datetime="2026-10-16T09:05:00+00:00">16.10.2026 09:05</time>', $row);
        $this->assertStringContainsString('border-status-pending', $row);
        $this->assertMatchesRegularExpression('/uppercase">New<\/span>/', $row);
        $this->assertStringNotContainsString('text-red-deep', $row);
        $this->assertStringContainsString('<caption class="sr-only">Feedback</caption>', $html);
    }

    public function test_a_suspended_sender_shows_the_suspension_status(): void
    {
        $sender = User::factory()->suspended()->create();
        $message = ContactMessage::factory()->for($sender)->create();

        $this->assertStringContainsString('<span class="text-xs text-red-deep">Suspended indefinitely</span>', $this->row($this->page()->getContent(), $message));
    }

    public function test_a_new_row_offers_mark_handled_naming_the_sender_and_time_and_carrying_the_view(): void
    {
        $this->travelTo('2026-10-16 09:05:00');
        $message = ContactMessage::factory()->for(User::factory()->create(['username' => 'lucia']))->create();
        ContactMessage::factory()->count(12)->create(['created_at' => now()->addMinute()]);

        $row = $this->row($this->page(['status' => 'new', 'page' => 2])->getContent(), $message);

        $this->assertStringContainsString('action="'.e(route('admin.feedback.status', [$message, 'status' => 'new', 'page' => 2])).'"', $row);
        $this->assertStringContainsString('<input type="hidden" name="_method" value="PATCH">', $row);
        $this->assertStringContainsString('<input type="hidden" name="target_status" value="handled">', $row);
        $this->assertMatchesRegularExpression('/border-red text-red[^"]*">\s*Mark handled<span class="sr-only"> — lucia, 16\.10\.2026 09:05<\/span>/', $row);
        $this->assertStringNotContainsString('Mark as new', $row);
    }

    public function test_a_handled_row_shows_the_handler_and_time_and_offers_mark_as_new(): void
    {
        $this->travelTo('2026-10-16 10:00:00');
        $admin = User::factory()->admin()->create(['username' => 'moderadora']);
        $message = ContactMessage::factory()->handled()->for($admin, 'handler')->create(['handled_at' => now()->subMinutes(30)]);

        $row = $this->row($this->page(['status' => 'handled'])->getContent(), $message);

        $this->assertStringContainsString('border-gold', $row);
        $this->assertMatchesRegularExpression('/uppercase">Handled<\/span>/', $row);
        $this->assertStringContainsString('by moderadora · <time datetime="2026-10-16T09:30:00+00:00" class="tabular-nums">16.10.2026 09:30</time>', $row);
        $this->assertStringContainsString('<input type="hidden" name="target_status" value="new">', $row);
        $this->assertMatchesRegularExpression('/border-rule text-ink[^"]*">\s*Mark as new<span class="sr-only"> — /', $row);
        $this->assertStringNotContainsString('Mark handled', $row);
    }

    public function test_a_message_handled_by_a_removed_admin_says_so(): void
    {
        $message = ContactMessage::factory()->handled()->create();

        $this->assertStringContainsString('by a removed admin · ', $this->row($this->page(['status' => 'handled'])->getContent(), $message));
    }

    public function test_subject_and_message_are_escaped(): void
    {
        $message = ContactMessage::factory()->create(['subject' => '<b>bold</b>', 'message' => '<script>alert(1)</script>']);

        $row = $this->row($this->page()->getContent(), $message);

        $this->assertStringContainsString('&lt;b&gt;bold&lt;/b&gt;', $row);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $row);
        $this->assertStringNotContainsString('<script>', $row);
    }

    public function test_the_feedback_nav_item_is_current_and_sits_between_reports_and_conversations(): void
    {
        $html = $this->page()->getContent();

        $this->assertMatchesRegularExpression('/href="'.preg_quote(route('admin.feedback'), '/').'"\s+aria-current="page"/', $html);
        $this->assertSeeInOrderHtml($html, ['href="'.route('admin.reports').'"', 'href="'.route('admin.feedback').'"', 'href="'.route('conversations.index').'"']);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function views(): array
    {
        return [
            'new' => ['new'],
            'handled' => ['handled'],
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

        $this->assertCount(12, $this->page(['status' => $status], $admin)->viewData('contactMessages'));
        $this->assertSame($few, $many);
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function page(array $query = [], ?User $admin = null): TestResponse
    {
        $response = $this->actingAs($admin ?? User::factory()->admin()->create())->get(route('admin.feedback', $query));
        $this->app['auth']->forgetGuards();

        return $response->assertOk();
    }

    /**
     * @param  array<string, mixed>  $query
     * @return list<int>
     */
    private function ids(array $query = []): array
    {
        return $this->page($query)->viewData('contactMessages')->pluck('id')->all();
    }

    private function mixedRows(int $count, string $status, User $admin): void
    {
        $otherAdmin = User::factory()->admin()->create();

        foreach (range(1, $count) as $index) {
            $sender = $index % 2 === 0 ? User::factory()->suspended()->create() : User::factory()->create();
            $factory = ContactMessage::factory()->for($sender);

            if ($status === 'handled') {
                $handler = [$admin->id, $otherAdmin->id, null][$index % 3];
                $factory->handled()->create(['handled_by' => $handler]);
            } else {
                $factory->create();
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

    private function row(string $html, ContactMessage $message): string
    {
        preg_match_all('/<tr class="block border-t.*?<\/tr>/s', $html, $rows);
        $needle = '/admin/feedback/'.$message->id.'/status';
        $matches = array_values(array_filter($rows[0], fn (string $row): bool => str_contains($row, $needle.'"') || str_contains($row, $needle.'?')));

        $this->assertCount(1, $matches, "Row of contact message {$message->id} not found once.");

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
