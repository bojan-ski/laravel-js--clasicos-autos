<?php

namespace Tests\Feature\Admin;

use App\Enums\NavCountStyle;
use App\Models\MessageReport;
use App\Models\User;
use App\Support\MainNavigation;
use App\Support\OpenReportsCounter;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OpenReportsCounterTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_it_counts_every_open_report_and_no_resolved_one(): void
    {
        MessageReport::factory()->count(2)->create();
        MessageReport::factory()->create()->message->delete();
        MessageReport::factory()->resolved()->create();

        $this->assertSame(3, app(OpenReportsCounter::class)->count(User::factory()->admin()->create()));
    }

    public function test_a_guest_or_an_app_user_gets_zero_without_a_query(): void
    {
        MessageReport::factory()->create();
        $appUser = User::factory()->create();
        $counter = app(OpenReportsCounter::class);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $counts = [$counter->count(null), $counter->count($appUser)];
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertSame([0, 0], $counts);
        $this->assertSame([], $queries);
    }

    public function test_the_reports_item_counts_open_reports_in_the_queue_style_with_the_open_noun(): void
    {
        $items = array_column($this->app->make(MainNavigation::class)->items(), null, 'key');

        $this->assertSame(OpenReportsCounter::class, $items['admin-reports']->counter);
        $this->assertSame(NavCountStyle::Queue, $items['admin-reports']->countStyle);
        $this->assertSame('app.header.count_labels.open', $items['admin-reports']->countLabelKey);
    }

    public function test_every_admin_page_shows_the_badge_and_none_at_zero(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('admin.account'))->assertOk()->assertDontSee(' open</span>', false);

        MessageReport::factory()->count(2)->create();

        foreach (['admin.account', 'admin.users', 'admin.listings', 'admin.reports', 'conversations.index'] as $route) {
            $html = $this->actingAs($admin)->get(route($route))->assertOk()->getContent();

            $this->assertMatchesRegularExpression('/href="'.preg_quote(route('admin.reports'), '/').'"[^>]*>\s*Reports\s*<span[^>]*data-nav-count[^>]*>\s*<span[^>]*>\s*<span[^>]*>2<span class="sr-only"> open<\/span><\/span>/', $html, $route);
        }
    }

    public function test_an_app_user_never_sees_the_reports_item(): void
    {
        MessageReport::factory()->count(2)->create();

        $this->actingAs(User::factory()->create())->get(route('profile'))
            ->assertOk()
            ->assertDontSee(route('admin.reports'), false)
            ->assertDontSee(' open</span>', false);
    }
}
