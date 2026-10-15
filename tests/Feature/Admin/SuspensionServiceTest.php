<?php

namespace Tests\Feature\Admin;

use App\Enums\UserStatus;
use App\Exceptions\InvalidUserStatusTransitionException;
use App\Models\CarListing;
use App\Models\User;
use App\Services\SuspensionService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\InsertsSessionRows;
use Tests\TestCase;

class SuspensionServiceTest extends TestCase
{
    use InsertsSessionRows;
    use LazilyRefreshDatabase;

    private const string REASON = 'Spamming every seller with the same offer.';

    /**
     * @return array<string, array{?int, ?string}>
     */
    public static function durations(): array
    {
        return [
            '7 days' => [7, '2026-10-22 10:00:00'],
            '30 days' => [30, '2026-11-14 10:00:00'],
            'indefinite' => [null, null],
        ];
    }

    #[DataProvider('durations')]
    public function test_suspend_writes_the_status_and_all_three_columns(?int $days, ?string $until): void
    {
        $this->travelTo('2026-10-15 10:00:00');
        $user = User::factory()->create();

        $this->service()->suspend($user, self::REASON, $days);

        $this->assertSame([
            'status' => 'suspended',
            'suspended_at' => '2026-10-15 10:00:00',
            'suspended_until' => $until,
            'suspension_reason' => self::REASON,
        ], $this->columns($user));
        $this->assertTrue($user->isSuspended());
        $this->assertSame($until, $user->suspended_until?->toDateTimeString());
    }

    public function test_suspend_deletes_only_the_users_session_rows_and_rotates_the_remember_token(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['remember_token' => 'known-remember-token'])->save();
        $this->insertSessionRow($user);
        $this->insertSessionRow($user);
        $kept = [$this->insertSessionRow(User::factory()->create()), $this->insertSessionRow(null)];
        sort($kept);

        $this->service()->suspend($user, self::REASON, 30);

        $this->assertSame($kept, $this->sessionRowIds());
        $token = DB::table('users')->where('id', $user->id)->value('remember_token');
        $this->assertNotSame('known-remember-token', $token);
        $this->assertSame(60, strlen($token));
    }

    public function test_a_suspend_unsuspend_cycle_changes_no_listing_and_hides_then_restores_the_approved_ones(): void
    {
        $user = User::factory()->create();
        $approved = CarListing::factory()->approved()->for($user, 'owner')->create();
        CarListing::factory()->for($user, 'owner')->create();
        CarListing::factory()->rejected()->for($user, 'owner')->create();
        $before = DB::table('car_listings')->orderBy('id')->get()->toArray();

        $this->service()->suspend($user, self::REASON, 7);

        $this->assertEquals($before, DB::table('car_listings')->orderBy('id')->get()->toArray());
        $this->assertFalse(CarListing::query()->publiclyVisible()->exists());

        $this->service()->unsuspend($user);

        $this->assertEquals($before, DB::table('car_listings')->orderBy('id')->get()->toArray());
        $this->assertSame([$approved->id], CarListing::query()->publiclyVisible()->pluck('id')->all());
    }

    public function test_unsuspend_clears_the_three_columns_and_reflects_on_the_passed_model(): void
    {
        $user = User::factory()->suspended(now()->addDays(30))->create();

        $this->service()->unsuspend($user);

        $this->assertSame(['status' => 'active', 'suspended_at' => null, 'suspended_until' => null, 'suspension_reason' => null], $this->columns($user));
        $this->assertFalse($user->isSuspended());
        $this->assertNull($user->suspension_reason);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function suspendedStates(): array
    {
        return [
            'running suspension' => ['+30 days'],
            'expired but not yet lifted' => ['-1 day'],
            'indefinite suspension' => ['indefinite'],
        ];
    }

    #[DataProvider('suspendedStates')]
    public function test_suspending_a_suspended_user_is_refused_and_changes_nothing(string $until): void
    {
        $user = User::factory()->suspended($until === 'indefinite' ? null : now()->modify($until))->create();
        $before = $this->columns($user);
        $this->insertSessionRow($user);

        try {
            $this->service()->suspend($user, self::REASON, 7);
            $caught = null;
        } catch (InvalidUserStatusTransitionException $exception) {
            $caught = $exception;
        }

        $this->assertInstanceOf(InvalidUserStatusTransitionException::class, $caught);
        $this->assertSame($before, $this->columns($user));
        $this->assertCount(1, $this->sessionRowIds());
    }

    public function test_unsuspending_an_active_user_is_refused_and_changes_nothing(): void
    {
        $user = User::factory()->create();

        $this->expectException(InvalidUserStatusTransitionException::class);

        try {
            $this->service()->unsuspend($user);
        } finally {
            $this->assertSame(UserStatus::Active, $user->fresh()->status);
        }
    }

    public function test_suspend_re_checks_the_state_under_the_lock_not_on_the_passed_model(): void
    {
        $user = User::factory()->create();
        DB::table('users')->where('id', $user->id)->update(['status' => 'suspended', 'suspended_at' => now(), 'suspension_reason' => 'Earlier reason.']);

        $this->expectException(InvalidUserStatusTransitionException::class);

        try {
            $this->service()->suspend($user, self::REASON, 7);
        } finally {
            $this->assertSame('Earlier reason.', $user->fresh()->suspension_reason);
        }
    }

    public function test_an_admin_is_never_suspended(): void
    {
        $admin = User::factory()->admin()->create();

        $this->expectException(LogicException::class);

        try {
            $this->service()->suspend($admin, self::REASON, null);
        } finally {
            $this->assertSame(UserStatus::Active, $admin->fresh()->status);
        }
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function liftCases(): array
    {
        return [
            'ended yesterday' => ['-1 day', true],
            'ends this second' => ['+0 seconds', true],
            'ends in a second' => ['+1 second', false],
            'indefinite' => ['indefinite', false],
            'active user' => ['active', false],
        ];
    }

    #[DataProvider('liftCases')]
    public function test_lift_if_expired_lifts_only_a_suspension_whose_end_has_come(string $until, bool $lifted): void
    {
        $this->freezeTime();
        $user = match ($until) {
            'active' => User::factory()->create(),
            'indefinite' => User::factory()->suspended()->create(),
            default => User::factory()->suspended(now()->modify($until))->create(),
        };
        $before = $this->columns($user);

        $this->assertSame($lifted, $this->service()->liftIfExpired($user));

        $expected = $lifted ? ['status' => 'active', 'suspended_at' => null, 'suspended_until' => null, 'suspension_reason' => null] : $before;
        $this->assertSame($expected, $this->columns($user));
        $this->assertSame($expected['status'] === 'suspended', $user->isSuspended());
    }

    public function test_lift_if_expired_never_overwrites_a_concurrent_unsuspend_and_syncs_the_passed_model(): void
    {
        $user = User::factory()->suspended(now()->subDay())->create();
        DB::table('users')->where('id', $user->id)->update(['status' => 'active', 'suspended_at' => null, 'suspended_until' => null, 'suspension_reason' => null]);
        $updatedAt = DB::table('users')->where('id', $user->id)->value('updated_at');

        $this->assertFalse($this->service()->liftIfExpired($user));

        $this->assertFalse($user->isSuspended());
        $this->assertSame($updatedAt, DB::table('users')->where('id', $user->id)->value('updated_at'));
    }

    public function test_lift_if_expired_does_nothing_for_a_user_deleted_meanwhile(): void
    {
        $user = User::factory()->suspended(now()->subDay())->create();
        DB::table('users')->where('id', $user->id)->delete();

        $this->assertFalse($this->service()->liftIfExpired($user));
    }

    public function test_lift_expired_lifts_every_expired_user_and_counts_them(): void
    {
        $this->freezeTime();
        $expired = User::factory()->suspended(now()->subDay())->create();
        $endsNow = User::factory()->suspended(now())->create();
        $future = User::factory()->suspended(now()->addDay())->create();
        $indefinite = User::factory()->suspended()->create();

        $this->assertSame(2, $this->service()->liftExpired());
        $this->assertSame(0, $this->service()->liftExpired());

        $this->assertSame(UserStatus::Active, $expired->fresh()->status);
        $this->assertSame(UserStatus::Active, $endsNow->fresh()->status);
        $this->assertSame(UserStatus::Suspended, $future->fresh()->status);
        $this->assertSame(UserStatus::Suspended, $indefinite->fresh()->status);
    }

    private function service(): SuspensionService
    {
        return $this->app->make(SuspensionService::class);
    }

    /**
     * @return array{status: string, suspended_at: ?string, suspended_until: ?string, suspension_reason: ?string}
     */
    private function columns(User $user): array
    {
        return (array) DB::table('users')->where('id', $user->id)->first(['status', 'suspended_at', 'suspended_until', 'suspension_reason']);
    }
}
