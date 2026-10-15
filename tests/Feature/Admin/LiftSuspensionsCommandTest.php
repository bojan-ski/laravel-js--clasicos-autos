<?php

namespace Tests\Feature\Admin;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class LiftSuspensionsCommandTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_it_lifts_every_ended_suspension_prints_the_count_and_leaves_the_rest(): void
    {
        $this->freezeTime();
        $ended = User::factory()->suspended(now()->subHour())->create();
        $endsNow = User::factory()->suspended(now())->create();
        $future = User::factory()->suspended(now()->addHour())->create();
        $indefinite = User::factory()->suspended()->create();

        $this->artisan('suspensions:lift')
            ->expectsOutput('Lifted 2 suspension(s).')
            ->assertSuccessful();

        $this->assertSame(UserStatus::Active, $ended->fresh()->status);
        $this->assertNull($ended->fresh()->suspension_reason);
        $this->assertSame(UserStatus::Active, $endsNow->fresh()->status);
        $this->assertSame(UserStatus::Suspended, $future->fresh()->status);
        $this->assertSame(UserStatus::Suspended, $indefinite->fresh()->status);
    }

    public function test_a_second_run_lifts_nothing(): void
    {
        User::factory()->suspended(now()->subDay())->create();
        $this->artisan('suspensions:lift')->assertSuccessful();

        $this->artisan('suspensions:lift')
            ->expectsOutput('Lifted 0 suspension(s).')
            ->assertSuccessful();
    }

    public function test_the_scheduler_runs_it_every_hour(): void
    {
        $events = array_values(array_filter(
            $this->app->make(Schedule::class)->events(),
            fn (Event $event): bool => str_ends_with((string) $event->command, 'suspensions:lift'),
        ));

        $this->assertCount(1, $events);
        $this->assertSame('0 * * * *', $events[0]->expression);
    }
}
