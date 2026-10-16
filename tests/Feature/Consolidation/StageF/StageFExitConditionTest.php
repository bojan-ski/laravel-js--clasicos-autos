<?php

namespace Tests\Feature\Consolidation\StageF;

use App\Enums\ContactMessageStatus;
use App\Enums\ListingStatus;
use App\Enums\ReportOutcome;
use App\Enums\ReportStatus;
use App\Enums\UserStatus;
use App\Models\CarListing;
use App\Models\CarMaker;
use App\Models\CarModel;
use App\Models\ContactMessage;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\MessageReport;
use App\Models\User;
use App\Support\SuspensionText;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\Support\InsertsSessionRows;
use Tests\TestCase;

class StageFExitConditionTest extends TestCase
{
    use InsertsSessionRows;
    use LazilyRefreshDatabase;

    private const string REASON = 'Asked several buyers for deposits outside the app.';

    private const array ADMIN_INDEX_PAGES = ['admin.users', 'admin.listings', 'admin.reports', 'admin.feedback', 'admin.makers'];

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Storage::fake('public');
        $this->travelTo('2026-10-16 10:00:00');

        $this->admin = User::factory()->admin()->create();
    }

    public function test_the_admin_lands_on_the_users_table_and_the_admin_pages_refuse_everyone_else(): void
    {
        $this->asGuest()->post(route('login.store'), ['email' => $this->admin->email, 'password' => 'password'])
            ->assertRedirect(route('admin.users'));
        $this->assertAuthenticatedAs($this->admin);

        $appUser = User::factory()->create();

        foreach (self::ADMIN_INDEX_PAGES as $page) {
            $this->as($appUser)->get(route($page))->assertForbidden()->assertSee(__('app.errors.403.title'));
            $this->asGuest()->get(route($page))->assertRedirect(route('login'));
        }
    }

    public function test_the_admin_suspends_a_user_for_thirty_days_which_ends_their_sessions_and_hides_their_listings(): void
    {
        $target = User::factory()->create(['username' => 'carla']);
        $target->forceFill(['remember_token' => 'known-remember-token'])->save();
        $approved = CarListing::factory()->approved()->for($target, 'owner')->count(2)->create();
        $pending = CarListing::factory()->for($target, 'owner')->create();
        $this->insertSessionRow($target);
        $this->insertSessionRow($target);
        $keptSession = $this->insertSessionRow(User::factory()->create());
        $report = $this->reportAgainst($target);
        $this->assertSame([$approved[0]->id, $approved[1]->id], $this->publicIdsOf($target));

        $this->as($this->admin)
            ->patch(route('admin.users.suspend', $target), ['reason' => self::REASON, 'duration' => '30_days'])
            ->assertRedirect(route('admin.users'))
            ->assertSessionHas('success', __('app.admin.users.flash.suspended', ['username' => 'carla']));

        $target->refresh();
        $this->assertSame(UserStatus::Suspended, $target->status);
        $this->assertSame('2026-11-15 10:00:00', $target->suspended_until->toDateTimeString());
        $this->assertSame(self::REASON, $target->suspension_reason);

        $this->assertSame([$keptSession], $this->sessionRowIds());
        $this->assertNotSame('known-remember-token', $target->remember_token);

        $this->assertSame([], $this->publicIdsOf($target));
        foreach ($approved as $listing) {
            $this->asGuest()->get(route('listings.show', $listing))->assertNotFound();
            $this->assertSame(ListingStatus::Approved, $listing->fresh()->status);
        }
        $this->assertSame(ListingStatus::Pending, $pending->fresh()->status);

        $report->refresh();
        $this->assertSame([ReportStatus::Resolved, ReportOutcome::UserSuspended, $this->admin->id], [$report->status, $report->outcome, $report->resolved_by]);

        $this->asGuest()->post(route('login.store'), ['email' => $target->email, 'password' => 'password'])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['email' => SuspensionText::notice($target)]);
        $this->assertGuest();

        $this->as($target)->get(route('profile'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['email' => SuspensionText::notice($target)]);
        $this->assertGuest();
    }

    public function test_the_hourly_command_lifts_the_suspension_at_its_end_and_restores_the_approved_listings_only(): void
    {
        $events = array_values(array_filter(
            $this->app->make(Schedule::class)->events(),
            fn (Event $event): bool => str_ends_with((string) $event->command, 'suspensions:lift'),
        ));
        $this->assertCount(1, $events);
        $this->assertSame('0 * * * *', $events[0]->expression);

        $until = now()->addDays(30);
        $target = User::factory()->suspended($until)->create();
        $approved = CarListing::factory()->approved()->for($target, 'owner')->count(2)->create();
        $pending = CarListing::factory()->for($target, 'owner')->create();

        $this->travelTo($until->copy()->subHour());
        $this->artisan('suspensions:lift')->assertSuccessful();
        $this->assertSame(UserStatus::Suspended, $target->fresh()->status);
        $this->assertSame([], $this->publicIdsOf($target));

        $this->travelTo($until);
        $this->artisan('suspensions:lift')->assertSuccessful();

        $this->assertSame(
            ['status' => 'active', 'suspended_at' => null, 'suspended_until' => null, 'suspension_reason' => null],
            (array) DB::table('users')->where('id', $target->id)->first(['status', 'suspended_at', 'suspended_until', 'suspension_reason']),
        );
        $this->assertSame([$approved[0]->id, $approved[1]->id], $this->publicIdsOf($target));
        $this->assertSame(ListingStatus::Pending, $pending->fresh()->status);
        $this->asGuest()->get(route('listings.show', $pending))->assertNotFound();
    }

    public function test_the_admin_works_the_queue_oldest_first_approving_rejecting_and_promoting(): void
    {
        $maker = CarMaker::factory()->create(['name' => 'Kaiser']);
        $model = CarModel::factory()->for($maker, 'maker')->create(['name' => 'Manhattan', 'year_from' => 1951, 'year_to' => 1955]);
        $custom = CarListing::factory()->customModel()->for($maker, 'maker')->create(['model' => 'Custom Roadster', 'year' => 1957, 'submitted_at' => now()->subDays(4)]);
        $first = CarListing::factory()->forCarModel($model)->create(['submitted_at' => now()->subDays(3)]);
        $second = CarListing::factory()->forCarModel($model)->create(['submitted_at' => now()->subDays(2)]);

        $queue = $this->as($this->admin)->get(route('admin.listings'))->assertOk();
        $this->assertSame([$custom->id, $first->id, $second->id], $queue->viewData('listings')->pluck('id')->all());
        $this->assertBadge($queue, 3, 'pending');

        $this->as($this->admin)
            ->patch(route('admin.listings.approve', [$first, 'return' => 'queue']), ['version' => $first->moderationVersion()])
            ->assertRedirect(route('admin.listings'))
            ->assertSessionHas('success', __('app.listings.moderation.approved'));
        $this->assertSame(ListingStatus::Approved, $first->fresh()->status);
        $this->asGuest()->get(route('listings.show', $first))->assertOk();

        $this->as($this->admin)
            ->patch(route('admin.listings.reject', [$second, 'return' => 'queue']), ['version' => $second->moderationVersion(), 'rejection_reason' => 'The photos do not show the engine.'])
            ->assertRedirect(route('admin.listings'))
            ->assertSessionHas('success', __('app.listings.moderation.rejected'));
        $this->assertSame(ListingStatus::Rejected, $second->fresh()->status);

        $submittedAt = $custom->submitted_at->toDateTimeString();
        $this->as($this->admin)
            ->patch(route('admin.listings.promote', $custom), ['name' => 'Roadster', 'year_from' => 1955, 'year_to' => 1960, 'version' => $custom->moderationVersion()])
            ->assertRedirect(route('admin.listings'))
            ->assertSessionHas('success');

        $promoted = CarModel::query()->where('car_maker_id', $maker->id)->where('name', 'Roadster')->sole();
        $custom->refresh();
        $this->assertSame([$promoted->id, 'Roadster', ListingStatus::Pending, $submittedAt], [$custom->car_model_id, $custom->model, $custom->status, $custom->submitted_at->toDateTimeString()]);
        $this->assertBadge($this->as($this->admin)->get(route('admin.listings'))->assertOk(), 1, 'pending');
    }

    public function test_the_admin_resolves_each_report_with_one_action(): void
    {
        $authorA = User::factory()->create();
        $authorB = User::factory()->create();
        $authorC = User::factory()->create();
        [$dismissed, $deleted] = [$this->reportAgainst($authorA), $this->reportAgainst($authorA)];
        [$suspendedFrom, $closedAlongside] = [$this->reportAgainst($authorB), $this->reportAgainst($authorB)];
        $untouched = $this->reportAgainst($authorC);

        $this->resolve($dismissed, ['outcome' => 'dismissed'])
            ->assertSessionHas('success', __('app.admin.reports.flash.dismissed'));
        $this->assertSame([ReportStatus::Resolved, ReportOutcome::Dismissed, $this->admin->id], $this->resolution($dismissed));
        $this->assertModelExists(Message::query()->findOrFail($dismissed->message_id));
        foreach ([$deleted, $suspendedFrom, $closedAlongside, $untouched] as $open) {
            $this->assertSame([ReportStatus::Open, null, null], $this->resolution($open));
        }

        $messageId = $deleted->message_id;
        $text = $deleted->message_text;
        $this->resolve($deleted, ['outcome' => 'message_deleted'])
            ->assertSessionHas('success', __('app.admin.reports.flash.message_deleted'));
        $this->assertNull(Message::query()->find($messageId));
        $deleted->refresh();
        $this->assertSame([null, $text], [$deleted->message_id, $deleted->message_text]);
        $this->assertSame([ReportStatus::Resolved, ReportOutcome::MessageDeleted, $this->admin->id], $this->resolution($deleted));

        $this->resolve($suspendedFrom, ['outcome' => 'user_suspended', 'reason' => self::REASON, 'duration' => '7_days'])
            ->assertSessionHas('success', __('app.admin.reports.flash.user_suspended'));
        $this->assertSame(UserStatus::Suspended, $authorB->fresh()->status);
        $this->assertSame('2026-10-23 10:00:00', $authorB->fresh()->suspended_until->toDateTimeString());
        foreach ([$suspendedFrom, $closedAlongside] as $closed) {
            $this->assertSame([ReportStatus::Resolved, ReportOutcome::UserSuspended, $this->admin->id], $this->resolution($closed));
        }
        $this->assertSame([ReportStatus::Open, null, null], $this->resolution($untouched));
        $this->assertSame(UserStatus::Active, $authorC->fresh()->status);

        $this->assertBadge($this->as($this->admin)->get(route('admin.reports'))->assertOk(), 1, 'open');
    }

    public function test_the_admin_reads_feedback_and_marks_it_handled_and_new_again(): void
    {
        $sender = User::factory()->create(['username' => 'pedro', 'email' => 'pedro@example.com']);

        $this->as($sender)
            ->post(route('contact.store'), ['subject' => 'Approval time', 'message' => 'How long does an approval take?'])
            ->assertRedirect(route('contact'))
            ->assertSessionHas('success', __('app.contact.flash.sent'));
        $message = ContactMessage::query()->sole();

        $inbox = $this->as($this->admin)->get(route('admin.feedback'))->assertOk()->assertSee(['pedro', 'pedro@example.com']);
        $this->assertSame([$message->id], $inbox->viewData('contactMessages')->pluck('id')->all());
        $this->assertBadge($inbox, 1, 'new');

        $this->as($this->admin)->patch(route('admin.feedback.status', $message), ['target_status' => 'handled'])
            ->assertRedirect(route('admin.feedback'))
            ->assertSessionHas('success', __('app.admin.feedback.flash.handled'));
        $message->refresh();
        $this->assertSame([ContactMessageStatus::Handled, '2026-10-16 10:00:00', $this->admin->id], [$message->status, $message->handled_at->toDateTimeString(), $message->handled_by]);
        $this->assertSame([], $this->as($this->admin)->get(route('admin.feedback'))->viewData('contactMessages')->pluck('id')->all());
        $this->assertSame([$message->id], $this->as($this->admin)->get(route('admin.feedback', ['status' => 'handled']))->viewData('contactMessages')->pluck('id')->all());

        $this->as($this->admin)->patch(route('admin.feedback.status', $message), ['target_status' => 'new'])
            ->assertSessionHas('success', __('app.admin.feedback.flash.new'));
        $message->refresh();
        $this->assertSame([ContactMessageStatus::New, null, null], [$message->status, $message->handled_at, $message->handled_by]);
    }

    public function test_the_admin_manages_makers_and_models_without_disturbing_the_listings_that_use_them(): void
    {
        $this->as($this->admin)->post(route('admin.makers.store'), ['name' => 'Tucker'])->assertRedirect(route('admin.makers'));
        $maker = CarMaker::query()->where('name', 'Tucker')->sole();
        $this->as($this->admin)->post(route('admin.makers.models.store', $maker), ['name' => 'Torpedo', 'year_from' => 1946, 'year_to' => 1949])
            ->assertRedirect(route('admin.makers.show', $maker));
        $model = CarModel::query()->where('car_maker_id', $maker->id)->where('name', 'Torpedo')->sole();
        $owner = User::factory()->create();
        $listing = CarListing::factory()->approved()->forCarModel($model)->for($owner, 'owner')->create(['year' => 1948]);
        $before = $this->reviewState($listing);

        $this->travel(1)->hours();
        $this->as($this->admin)->patch(route('admin.makers.models.update', [$maker, $model]), ['name' => 'Torpedo Sedan', 'year_from' => 1946, 'year_to' => 1949])
            ->assertRedirect(route('admin.makers.show', $maker))
            ->assertSessionHasNoErrors();
        $this->assertSame('Torpedo Sedan', $listing->fresh()->model);
        $this->assertSame($before, $this->reviewState($listing));

        $this->as($this->admin)->patch(route('admin.makers.models.active', [$maker, $model]), ['active' => '0'])->assertSessionHas('success');
        $createModels = $this->as($owner)->get(route('listings.create'))->assertOk()->viewData('options')->modelConfig['models'][$maker->id] ?? [];
        $this->assertNotContains($model->id, array_column($createModels, 'id'));

        $this->as($this->admin)->patch(route('admin.makers.active', $maker), ['active' => '0'])->assertSessionHas('success');
        $this->assertNotContains($maker->id, $this->asGuest()->get(route('listings.index'))->viewData('makers')->pluck('id')->all());
        $listing->refresh();
        $this->assertSame([$maker->id, $model->id], [$listing->car_maker_id, $listing->car_model_id]);
        $this->asGuest()->get(route('listings.show', $listing))->assertOk();

        $this->as($this->admin)->delete(route('admin.makers.models.destroy', [$maker, $model]))
            ->assertForbidden()
            ->assertSee(__('app.errors.403.title'));
        $this->assertModelExists($model);

        $unusedModel = CarModel::factory()->for($maker, 'maker')->create(['name' => 'Talisman']);
        $unusedMaker = CarMaker::factory()->create(['name' => 'Crosley']);
        $this->as($this->admin)->delete(route('admin.makers.models.destroy', [$maker, $unusedModel]))
            ->assertRedirect(route('admin.makers.show', $maker))
            ->assertSessionHas('success', __('app.admin.models.flash.deleted', ['name' => 'Talisman']));
        $this->as($this->admin)->delete(route('admin.makers.destroy', $unusedMaker))
            ->assertRedirect(route('admin.makers'))
            ->assertSessionHas('success', __('app.admin.makers.flash.deleted', ['name' => 'Crosley']));
        $this->assertModelMissing($unusedModel);
        $this->assertModelMissing($unusedMaker);
    }

    private function reportAgainst(User $author): MessageReport
    {
        $conversation = Conversation::factory()->for($author, 'sender')->create();

        return MessageReport::factory()->create(['message_id' => Message::factory()->for($conversation)->create()->id]);
    }

    /**
     * @param  array<string, string>  $payload
     */
    private function resolve(MessageReport $report, array $payload): TestResponse
    {
        return $this->as($this->admin)
            ->patch(route('admin.reports.resolve', $report), $payload)
            ->assertRedirect(route('admin.reports'));
    }

    /**
     * @return array{ReportStatus, ?ReportOutcome, ?int}
     */
    private function resolution(MessageReport $report): array
    {
        $report->refresh();

        return [$report->status, $report->outcome, $report->resolved_by];
    }

    /**
     * @return array{string, string, string, string}
     */
    private function reviewState(CarListing $listing): array
    {
        $listing = $listing->fresh();

        return [$listing->status->value, $listing->submitted_at->toDateTimeString(), $listing->updated_at->toDateTimeString(), $listing->approved_at->toDateTimeString()];
    }

    /**
     * @return list<int>
     */
    private function publicIdsOf(User $owner): array
    {
        $ids = $this->asGuest()->get(route('listings.index'))->assertOk()->viewData('listings')->where('user_id', $owner->id)->pluck('id')->all();
        sort($ids);

        return $ids;
    }

    private function assertBadge(TestResponse $response, int $count, string $noun): void
    {
        $response->assertSee('>'.$count.'<span class="sr-only"> '.__("app.header.count_labels.{$noun}").'</span>', false);
    }

    private function as(User $user): static
    {
        $this->app['auth']->forgetGuards();

        return $this->actingAs($user);
    }

    private function asGuest(): static
    {
        $this->flushSession();
        $this->app['auth']->forgetGuards();

        return $this;
    }
}
