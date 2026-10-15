<?php

namespace Tests\Feature\Conversations;

use App\Broadcasting\UserChannel;
use App\Models\User;
use Illuminate\Broadcasting\Broadcasters\NullBroadcaster;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Tests\TestCase;

class UserChannelTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_the_user_channel_is_registered_to_the_channel_class(): void
    {
        $this->assertSame(UserChannel::class, Broadcast::driver()->getChannels()->get('user.{id}'));
    }

    public function test_tests_always_run_on_the_null_broadcaster(): void
    {
        $this->assertNull(config('broadcasting.default'));
        $this->assertInstanceOf(NullBroadcaster::class, Broadcast::driver());
    }

    public function test_an_active_app_user_joins_their_own_channel(): void
    {
        $user = User::factory()->create();

        $this->assertTrue((new UserChannel)->join($user, (string) $user->id));
    }

    public function test_an_active_app_user_is_refused_another_users_channel(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->assertFalse((new UserChannel)->join($user, (string) $other->id));
    }

    public function test_an_admin_is_refused_their_own_channel(): void
    {
        $admin = User::factory()->admin()->create();

        $this->assertFalse((new UserChannel)->join($admin, (string) $admin->id));
    }

    public function test_a_suspended_app_user_is_refused_their_own_channel(): void
    {
        $user = User::factory()->suspended()->create();

        $this->assertFalse((new UserChannel)->join($user, (string) $user->id));
    }
}
