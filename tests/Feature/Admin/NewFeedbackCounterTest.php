<?php

namespace Tests\Feature\Admin;

use App\Models\ContactMessage;
use App\Models\User;
use App\Support\NewFeedbackCounter;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class NewFeedbackCounterTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_it_counts_every_new_message_and_no_handled_one(): void
    {
        ContactMessage::factory()->count(3)->create();
        ContactMessage::factory()->for(User::factory()->suspended())->create();
        ContactMessage::factory()->handled()->count(2)->create();

        $this->assertSame(4, app(NewFeedbackCounter::class)->count(User::factory()->admin()->create()));
    }

    public function test_a_guest_or_an_app_user_gets_zero_without_a_query(): void
    {
        ContactMessage::factory()->create();
        $appUser = User::factory()->create();
        $counter = app(NewFeedbackCounter::class);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $counts = [$counter->count(null), $counter->count($appUser)];
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertSame([0, 0], $counts);
        $this->assertSame([], $queries);
    }

    public function test_every_admin_page_shows_the_badge_and_none_at_zero(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('admin.account'))->assertOk()->assertDontSee(' new</span>', false);

        ContactMessage::factory()->count(3)->create();
        ContactMessage::factory()->handled()->create();

        foreach (['admin.account', 'admin.users', 'admin.listings', 'admin.reports', 'admin.feedback', 'conversations.index'] as $route) {
            $html = $this->actingAs($admin)->get(route($route))->assertOk()->getContent();

            $this->assertMatchesRegularExpression('/href="'.preg_quote(route('admin.feedback'), '/').'"[^>]*>\s*Feedback\s*<span[^>]*data-nav-count[^>]*>\s*<span[^>]*>\s*<span[^>]*>3<span class="sr-only"> new<\/span><\/span>/', $html, $route);
        }
    }

    public function test_an_app_user_never_sees_the_feedback_item(): void
    {
        ContactMessage::factory()->count(2)->create();

        $this->actingAs(User::factory()->create())->get(route('profile'))
            ->assertOk()
            ->assertDontSee(route('admin.feedback'), false)
            ->assertDontSee(' new</span>', false);
    }
}
