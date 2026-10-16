<?php

namespace Tests\Feature\Consolidation\StageF;

use App\Enums\ReportOutcome;
use App\Models\CarListing;
use App\Models\ContactMessage;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\MessageReport;
use App\Models\User;
use App\Support\SuspensionText;
use Generator;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\InsertsSessionRows;
use Tests\TestCase;

class SuspensionLifecycleMatrixTest extends TestCase
{
    use InsertsSessionRows;
    use LazilyRefreshDatabase;

    private const string NOW = '2026-10-16 10:00:00';

    private const string OLD_REASON = 'An earlier warning about spam.';

    private const string REASON = 'Asked several buyers for deposits outside the app.';

    private const string TOKEN = 'known-remember-token';

    private const array DURATIONS = ['7_days' => '2026-10-23 10:00:00', '30_days' => '2026-11-15 10:00:00', 'indefinite' => null];

    private User $admin;

    private User $previousAdmin;

    private User $bystander;

    private CarListing $approved;

    private CarListing $pending;

    private CarListing $rejected;

    private Conversation $buying;

    private Conversation $selling;

    private MessageReport $openAgainst;

    private MessageReport $resolvedAgainst;

    private MessageReport $filed;

    private MessageReport $othersReport;

    private string $bystanderSession;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Storage::fake('public');
        $this->travelTo(self::NOW);

        $this->admin = User::factory()->admin()->create();
        $this->previousAdmin = User::factory()->admin()->create();
        $this->bystander = User::factory()->create();
    }

    /**
     * @return Generator<string, array{string, string, string}>
     */
    public static function cells(): Generator
    {
        $rows = [
            'suspend_7_days' => ['active' => 'S', 'timed' => 'G', 'expired' => 'G', 'indefinite' => 'G'],
            'suspend_30_days' => ['active' => 'S', 'timed' => 'G', 'expired' => 'G', 'indefinite' => 'G'],
            'suspend_indefinite' => ['active' => 'S', 'timed' => 'G', 'expired' => 'G', 'indefinite' => 'G'],
            'unsuspend' => ['active' => 'G', 'timed' => 'A', 'expired' => 'A', 'indefinite' => 'A'],
            'lift' => ['active' => 'U', 'timed' => 'U', 'expired' => 'A', 'indefinite' => 'U'],
            'sign_in' => ['active' => 'IN', 'timed' => 'OUT', 'expired' => 'A+IN', 'indefinite' => 'OUT'],
            'next_request' => ['active' => 'IN', 'timed' => 'OUT', 'expired' => 'A+IN', 'indefinite' => 'OUT'],
            'delete' => ['active' => 'D', 'timed' => 'D', 'expired' => 'D', 'indefinite' => 'D'],
            'self_delete' => ['active' => "D'", 'timed' => 'OUT', 'expired' => "D'", 'indefinite' => 'OUT'],
        ];

        foreach ($rows as $action => $byState) {
            foreach ($byState as $from => $cell) {
                yield "{$action} from {$from}" => [$action, $from, $cell];
            }
        }
    }

    #[DataProvider('cells')]
    public function test_each_action_from_each_state(string $action, string $from, string $cell): void
    {
        $target = $this->targetIn($from);
        $before = $this->snapshot($target);
        $notice = $target->isSuspended() ? SuspensionText::notice($target) : null;

        if (str_starts_with($cell, 'D')) {
            $this->assertTargetRowsExist();
        }

        $response = $this->perform($action, $target);

        match ($cell) {
            'S' => $this->assertSuspendedCell($response, $target, $before, substr($action, strlen('suspend_'))),
            'A' => $this->assertActivatedCell($response, $target, $before, $action),
            'G' => $this->assertGuardCell($response, $target, $before, $from === 'active' ? 'not_suspended' : 'already_suspended'),
            'U' => $this->assertSame($before, $this->snapshot($target)),
            'IN' => $this->assertServedCell($response, $target, $before, $action),
            'A+IN' => $this->assertLiftedAndServedCell($response, $target, $before, $action),
            'OUT' => $this->assertSignedOutCell($response, $target, $before, $notice, $action),
            'D' => $this->assertDeletedCell($response, $target),
            "D'" => $this->assertSelfDeletedCell($response, $target),
        };
    }

    /**
     * @return Generator<string, array{string, string}>
     */
    public static function refusedActors(): Generator
    {
        foreach (['admin.users.suspend', 'admin.users.unsuspend', 'admin.users.destroy'] as $route) {
            foreach (['guest', 'app user', 'other admin', 'self', 'suspended actor', 'suspended actor, missing id'] as $actor) {
                yield "{$route} by {$actor}" => [$route, $actor];
            }
        }
    }

    #[DataProvider('refusedActors')]
    public function test_a_refused_actor_changes_nothing(string $route, string $actor): void
    {
        $target = match ($actor) {
            'other admin' => $this->previousAdmin,
            'self' => $this->admin,
            default => $this->targetIn($route === 'admin.users.unsuspend' ? 'timed' : 'active'),
        };
        $before = $this->snapshot($target);
        $suspendedActor = User::factory()->suspended(now()->addDays(3))->create();

        match ($actor) {
            'guest' => null,
            'app user' => $this->actingAs(User::factory()->create()),
            'other admin', 'self' => $this->actingAs($this->admin),
            default => $this->actingAs($suspendedActor),
        };

        $response = $this->call(
            $route === 'admin.users.destroy' ? 'DELETE' : 'PATCH',
            route($route, $actor === 'suspended actor, missing id' ? 999999 : $target),
            ['reason' => self::REASON, 'duration' => '7_days'],
        );

        match ($actor) {
            'guest', 'suspended actor', 'suspended actor, missing id' => $response->assertRedirect(route('login')),
            default => $response->assertForbidden()->assertSee(__('app.errors.403.title')),
        };

        if (str_starts_with($actor, 'suspended actor')) {
            $this->assertGuest();
        }

        $this->assertModelExists($target);
        $this->assertSame($before, $this->snapshot($target));
    }

    private function targetIn(string $state): User
    {
        $target = match ($state) {
            'active' => User::factory()->create(),
            'timed' => User::factory()->suspended(now()->addDays(5))->create(['suspended_at' => now()->subDays(25), 'suspension_reason' => self::OLD_REASON]),
            'expired' => User::factory()->suspended(now())->create(['suspended_at' => now()->subDays(30), 'suspension_reason' => self::OLD_REASON]),
            'indefinite' => User::factory()->suspended()->create(['suspended_at' => now()->subDays(10), 'suspension_reason' => self::OLD_REASON]),
        };
        $target->forceFill(['remember_token' => self::TOKEN])->save();

        $this->approved = CarListing::factory()->approved()->for($target, 'owner')->create();
        $this->pending = CarListing::factory()->for($target, 'owner')->create();
        $this->rejected = CarListing::factory()->rejected()->for($target, 'owner')->create();
        foreach ([$this->approved, $this->pending, $this->rejected] as $listing) {
            Storage::disk('public')->put("listings/{$listing->id}/photo.webp", 'x');
        }

        $this->insertSessionRow($target);
        $this->insertSessionRow($target);
        $this->bystanderSession = $this->insertSessionRow($this->bystander);

        $this->bystander->bookmarks()->attach($this->approved->id);

        $this->buying = Conversation::factory()->for($target, 'sender')->create();
        $this->openAgainst = MessageReport::factory()->create(['message_id' => Message::factory()->for($this->buying)->create()->id]);
        $this->resolvedAgainst = MessageReport::factory()->resolved()->create([
            'message_id' => Message::factory()->for($this->buying)->create()->id,
            'resolved_by' => $this->previousAdmin->id,
            'resolved_at' => now()->subDays(3),
        ]);
        $this->selling = Conversation::factory()->for($this->bystander, 'sender')->for($this->approved, 'listing')->create();
        $this->filed = MessageReport::factory()->create(['message_id' => Message::factory()->for($this->selling)->create()->id]);
        $this->othersReport = MessageReport::factory()->create();
        ContactMessage::factory()->for($target)->create();

        return $target;
    }

    private function perform(string $action, User $target): ?TestResponse
    {
        return match ($action) {
            'suspend_7_days', 'suspend_30_days', 'suspend_indefinite' => $this->actingAs($this->admin)->patch(
                route('admin.users.suspend', $target),
                ['reason' => '  '.self::REASON.'  ', 'duration' => substr($action, strlen('suspend_'))],
            ),
            'unsuspend' => $this->actingAs($this->admin)->patch(route('admin.users.unsuspend', $target)),
            'lift' => $this->liftAndReturnNothing(),
            'sign_in' => $this->post(route('login.store'), ['email' => $target->email, 'password' => 'password']),
            'next_request' => $this->actingAs($target)->get(route('profile')),
            'delete' => $this->actingAs($this->admin)->delete(route('admin.users.destroy', $target)),
            'self_delete' => $this->actingAs($target)->delete(route('profile.destroy'), ['delete_password' => 'password']),
        };
    }

    private function liftAndReturnNothing(): null
    {
        $this->artisan('suspensions:lift')->assertSuccessful();

        return null;
    }

    /**
     * @param  array<string, mixed>  $before
     */
    private function assertSuspendedCell(TestResponse $response, User $target, array $before, string $duration): void
    {
        $response->assertRedirect(route('admin.users'))
            ->assertSessionHas('success', __('app.admin.users.flash.suspended', ['username' => $target->username]));
        $after = $this->snapshot($target);

        $this->assertSame(['status' => 'suspended', 'suspended_at' => self::NOW, 'suspended_until' => self::DURATIONS[$duration], 'suspension_reason' => self::REASON], $after['columns']);
        $this->assertSame([$this->bystanderSession], $after['sessions']);
        $this->assertNotSame(self::TOKEN, $after['token']);
        $this->assertSame($before['listings'], $after['listings']);
        $this->assertSame([$this->openAgainst->id => ['resolved', ReportOutcome::UserSuspended->value, $this->admin->id, self::NOW, $this->openAgainst->message_id]] + $before['reports'], $after['reports']);
        $this->assertSame($before['conversation'], $after['conversation']);
        $this->assertSame($before['bookmarks'], $after['bookmarks']);
        $this->assertVisibility(approvedIsPublic: false);
    }

    /**
     * @param  array<string, mixed>  $before
     */
    private function assertActivatedCell(?TestResponse $response, User $target, array $before, string $action): void
    {
        if ($action === 'unsuspend') {
            $response->assertRedirect(route('admin.users'))
                ->assertSessionHas('success', __('app.admin.users.flash.unsuspended', ['username' => $target->username]));
        }

        $this->assertActivated($target, $before);
    }

    /**
     * @param  array<string, mixed>  $before
     */
    private function assertActivated(User $target, array $before): void
    {
        $after = $this->snapshot($target);

        $this->assertSame(['status' => 'active', 'suspended_at' => null, 'suspended_until' => null, 'suspension_reason' => null], $after['columns']);
        $this->assertSame([...$before, 'columns' => $after['columns']], $after);
        $this->assertVisibility(approvedIsPublic: true);
    }

    /**
     * @param  array<string, mixed>  $before
     */
    private function assertGuardCell(TestResponse $response, User $target, array $before, string $flash): void
    {
        $response->assertRedirect(route('admin.users'))->assertSessionHas('error', __("app.admin.users.flash.{$flash}"));

        $this->assertSame($before, $this->snapshot($target));
    }

    /**
     * @param  array<string, mixed>  $before
     */
    private function assertServedCell(TestResponse $response, User $target, array $before, string $action): void
    {
        $action === 'sign_in' ? $response->assertRedirect(route('profile')) : $response->assertOk();
        $this->assertAuthenticatedAs($target);

        $this->assertSame($before, $this->snapshot($target));
    }

    /**
     * @param  array<string, mixed>  $before
     */
    private function assertLiftedAndServedCell(TestResponse $response, User $target, array $before, string $action): void
    {
        $action === 'sign_in' ? $response->assertRedirect(route('profile')) : $response->assertOk();
        $this->assertAuthenticatedAs($target);

        $this->assertActivated($target, $before);
    }

    /**
     * @param  array<string, mixed>  $before
     */
    private function assertSignedOutCell(TestResponse $response, User $target, array $before, string $notice, string $action): void
    {
        $response->assertRedirect(route('login'))->assertSessionHasErrors(['email' => $notice]);
        $this->assertGuest();
        $after = $this->snapshot($target);

        if (in_array($action, ['next_request', 'self_delete'], true)) {
            $this->assertNotSame(self::TOKEN, $after['token']);
            $before['token'] = $after['token'];
        }

        $this->assertSame($before, $after);
    }

    private function assertDeletedCell(TestResponse $response, User $target): void
    {
        $response->assertRedirect(route('admin.users'))->assertSessionHas('success', __('app.admin.users.flash.deleted'));

        $this->assertTargetRowsGone($target);
    }

    private function assertSelfDeletedCell(TestResponse $response, User $target): void
    {
        $response->assertRedirect(route('home'))->assertSessionHas('success', __('app.profile.delete.deleted'));
        $this->assertGuest();

        $this->assertTargetRowsGone($target);
    }

    private function assertTargetRowsExist(): void
    {
        foreach ([$this->approved, $this->pending, $this->rejected, $this->buying, $this->selling, $this->openAgainst, $this->resolvedAgainst, $this->filed] as $model) {
            $this->assertModelExists($model);
        }
        $this->assertSame([2, 1], [$this->buying->messages()->count(), $this->selling->messages()->count()]);
        $this->assertSame([$this->approved->id], DB::table('user_bookmarks')->where('user_id', $this->bystander->id)->pluck('car_listing_id')->all());
    }

    private function assertTargetRowsGone(User $target): void
    {
        $this->assertModelMissing($target);
        foreach ([$this->buying, $this->selling] as $conversation) {
            $this->assertModelMissing($conversation);
            $this->assertSame(0, Message::query()->where('conversation_id', $conversation->id)->count());
        }
        $this->assertSame(0, DB::table('user_bookmarks')->where('car_listing_id', $this->approved->id)->count());
        foreach ([$this->approved, $this->pending, $this->rejected] as $listing) {
            $this->assertModelMissing($listing);
            $this->assertFalse(Storage::disk('public')->directoryExists("listings/{$listing->id}"));
        }
        foreach ([$this->openAgainst, $this->resolvedAgainst, $this->filed] as $report) {
            $this->assertModelMissing($report);
        }
        $this->assertModelExists($this->othersReport);
        $this->assertSame(0, ContactMessage::query()->where('user_id', $target->id)->count());
        $this->assertSame([$this->bystanderSession], $this->sessionRowIds());
    }

    private function assertVisibility(bool $approvedIsPublic): void
    {
        $this->app['auth']->forgetGuards();
        $this->flushSession();

        $this->get(route('listings.show', $this->approved))->assertStatus($approvedIsPublic ? 200 : 404);
        $this->get(route('listings.show', $this->pending))->assertNotFound();
        $this->get(route('listings.show', $this->rejected))->assertNotFound();
        $this->assertSame($approvedIsPublic, in_array($this->approved->id, $this->get(route('listings.index'))->viewData('listings')->pluck('id')->all(), true));
    }

    /**
     * @return array{columns: array<string, ?string>, token: ?string, sessions: list<string>, listings: array<int, string>, reports: array<int, list<int|string|null>>, conversation: array{int, int}, bookmarks: list<int>}
     */
    private function snapshot(User $target): array
    {
        $listings = [$this->approved ?? null, $this->pending ?? null, $this->rejected ?? null];
        $reports = [$this->openAgainst ?? null, $this->resolvedAgainst ?? null, $this->filed ?? null, $this->othersReport ?? null];

        return [
            'columns' => (array) DB::table('users')->where('id', $target->id)->first(['status', 'suspended_at', 'suspended_until', 'suspension_reason']),
            'token' => DB::table('users')->where('id', $target->id)->value('remember_token'),
            'sessions' => $this->sessionRowIds(),
            'listings' => DB::table('car_listings')->whereIn('id', array_filter(array_map(fn (?CarListing $listing): ?int => $listing?->id, $listings)))->orderBy('id')->pluck('status', 'id')->all(),
            'reports' => DB::table('message_reports')
                ->whereIn('id', array_filter(array_map(fn (?MessageReport $report): ?int => $report?->id, $reports)))
                ->orderBy('id')
                ->get(['id', 'status', 'outcome', 'resolved_by', 'resolved_at', 'message_id'])
                ->mapWithKeys(fn (object $row): array => [$row->id => [$row->status, $row->outcome, $row->resolved_by, $row->resolved_at, $row->message_id]])
                ->all(),
            'conversation' => [Conversation::query()->count(), Message::query()->count()],
            'bookmarks' => DB::table('user_bookmarks')->where('user_id', $this->bystander->id)->pluck('car_listing_id')->all(),
        ];
    }
}
