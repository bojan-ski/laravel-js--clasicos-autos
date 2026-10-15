<?php

namespace Tests\Feature\Admin;

use App\Enums\ListingStatus;
use App\Enums\UserStatus;
use App\Models\CarListing;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\InsertsSessionRows;
use Tests\TestCase;

class UserWritesTest extends TestCase
{
    use InsertsSessionRows;
    use LazilyRefreshDatabase;

    private const string REASON = 'Spamming every seller with the same offer.';

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Storage::fake('public');
        $this->admin = User::factory()->admin()->create();
    }

    /**
     * @return array<string, array{string, ?string}>
     */
    public static function durations(): array
    {
        return [
            '7 days' => ['7_days', '2026-10-22 10:00:00'],
            '30 days' => ['30_days', '2026-11-14 10:00:00'],
            'indefinite' => ['indefinite', null],
        ];
    }

    #[DataProvider('durations')]
    public function test_an_admin_suspends_an_app_user_and_returns_to_the_same_table_page(string $duration, ?string $until): void
    {
        $this->travelTo('2026-10-15 10:00:00');
        $user = User::factory()->create(['username' => 'carla']);

        $this->actingAs($this->admin)
            ->patch(route('admin.users.suspend', [$user, 'search' => 'car', 'page' => 2]), ['reason' => self::REASON, 'duration' => $duration])
            ->assertRedirect(route('admin.users', ['search' => 'car', 'page' => 2]))
            ->assertSessionHas('success', 'carla has been suspended.');

        $this->assertSame(
            ['status' => 'suspended', 'suspended_at' => '2026-10-15 10:00:00', 'suspended_until' => $until, 'suspension_reason' => self::REASON],
            $this->columns($user),
        );
    }

    public function test_a_suspension_hides_the_users_approved_listings_from_the_index_and_ignores_server_controlled_keys(): void
    {
        $user = User::factory()->create();
        $listing = CarListing::factory()->approved()->for($user, 'owner')->create(['name' => 'Hidden Bel Air']);
        $this->get(route('listings.index'))->assertSee('Hidden Bel Air');

        $this->actingAs($this->admin)->patch(route('admin.users.suspend', $user), [
            'reason' => self::REASON,
            'duration' => '7_days',
            'status' => 'active',
            'suspended_until' => '2099-01-01 00:00:00',
            'role' => 'admin',
        ])->assertSessionHas('success');
        $this->app['auth']->forgetGuards();

        $this->assertSame(UserStatus::Suspended, $user->fresh()->status);
        $this->assertTrue($user->fresh()->isAppUser());
        $this->assertSame(now()->addDays(7)->toDateString(), $user->fresh()->suspended_until->toDateString());
        $this->assertSame(ListingStatus::Approved, $listing->fresh()->status);
        $this->get(route('listings.index'))->assertDontSee('Hidden Bel Air');
    }

    /**
     * @return array<string, array{array<string, string>, string}>
     */
    public static function invalidSuspensions(): array
    {
        return [
            'missing reason' => [['duration' => '7_days'], 'The reason field is required.'],
            'whitespace reason' => [['reason' => '   ', 'duration' => '7_days'], 'The reason field is required.'],
            'reason over 500' => [['reason' => str_repeat('a', 501), 'duration' => '7_days'], 'The reason field must not be greater than 500 characters.'],
            'missing duration' => [['reason' => 'Abuse.'], 'The duration field is required.'],
            'unknown duration' => [['reason' => 'Abuse.', 'duration' => '3_days'], 'The selected duration is invalid.'],
        ];
    }

    /**
     * @param  array<string, string>  $payload
     */
    #[DataProvider('invalidSuspensions')]
    public function test_an_invalid_suspension_returns_to_the_table_with_the_error_flash_and_changes_nothing(array $payload, string $message): void
    {
        $user = User::factory()->create();

        $this->actingAs($this->admin)
            ->patch(route('admin.users.suspend', [$user, 'search' => 'car', 'page' => 3]), $payload)
            ->assertRedirect(route('admin.users', ['search' => 'car', 'page' => 3]))
            ->assertSessionHas('error', $message)
            ->assertSessionDoesntHaveErrors();

        $this->assertSame(UserStatus::Active, $user->fresh()->status);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function refusedActors(): array
    {
        $cases = [];

        foreach (['admin.users.suspend' => 'patch', 'admin.users.unsuspend' => 'patch', 'admin.users.destroy' => 'delete'] as $route => $method) {
            $cases["{$route} by an app user"] = [$route, 'app user'];
            $cases["{$route} on another admin"] = [$route, 'other admin'];
            $cases["{$route} on themself"] = [$route, 'self'];
        }

        return $cases;
    }

    #[DataProvider('refusedActors')]
    public function test_a_refused_write_answers_403_before_validation_and_changes_nothing(string $route, string $case): void
    {
        $actor = $case === 'app user' ? User::factory()->create() : $this->admin;
        $target = match ($case) {
            'app user' => $route === 'admin.users.unsuspend' ? User::factory()->suspended()->create() : User::factory()->create(),
            'other admin' => User::factory()->admin()->create(),
            'self' => $this->admin,
        };
        $before = $this->columns($target);

        $this->actingAs($actor)
            ->call($route === 'admin.users.destroy' ? 'DELETE' : 'PATCH', route($route, $target), [])
            ->assertForbidden()
            ->assertSee(__('app.errors.403.title'))
            ->assertSessionDoesntHaveErrors()
            ->assertSessionMissing('error');

        $this->assertModelExists($target);
        $this->assertSame($before, $this->columns($target));
    }

    public function test_an_unknown_or_non_numeric_user_is_not_found(): void
    {
        $this->actingAs($this->admin);

        $this->patch(route('admin.users.suspend', 999999), ['reason' => 'Abuse.', 'duration' => '7_days'])->assertNotFound();
        $this->patch(route('admin.users.unsuspend', 999999))->assertNotFound();
        $this->delete(route('admin.users.destroy', 999999))->assertNotFound();
        $this->patch('/admin/users/abc/suspend')->assertNotFound();
        $this->delete('/admin/users/abc')->assertNotFound();
    }

    public function test_suspending_a_suspended_user_flashes_an_error_and_changes_nothing(): void
    {
        $user = User::factory()->suspended(now()->addDays(3))->create();
        $before = $this->columns($user);

        $this->actingAs($this->admin)
            ->patch(route('admin.users.suspend', $user), ['reason' => self::REASON, 'duration' => '30_days'])
            ->assertRedirect(route('admin.users'))
            ->assertSessionHas('error', 'This user is already suspended.');

        $this->assertSame($before, $this->columns($user));
    }

    public function test_an_admin_unsuspends_and_the_approved_listings_are_public_again_at_once(): void
    {
        $user = User::factory()->suspended()->create(['username' => 'pedro']);
        CarListing::factory()->approved()->for($user, 'owner')->create(['name' => 'Back Bel Air']);
        $pending = CarListing::factory()->for($user, 'owner')->create();
        $rejected = CarListing::factory()->rejected()->for($user, 'owner')->create();

        $this->actingAs($this->admin)
            ->patch(route('admin.users.unsuspend', [$user, 'page' => 2]))
            ->assertRedirect(route('admin.users', ['page' => 2]))
            ->assertSessionHas('success', 'pedro is active again.');
        $this->app['auth']->forgetGuards();

        $this->assertSame(['status' => 'active', 'suspended_at' => null, 'suspended_until' => null, 'suspension_reason' => null], $this->columns($user));
        $this->assertSame(ListingStatus::Pending, $pending->fresh()->status);
        $this->assertSame(ListingStatus::Rejected, $rejected->fresh()->status);
        $this->get(route('listings.index'))->assertSee('Back Bel Air');
    }

    public function test_unsuspending_an_active_user_flashes_an_error(): void
    {
        $user = User::factory()->create();

        $this->actingAs($this->admin)
            ->patch(route('admin.users.unsuspend', $user))
            ->assertRedirect(route('admin.users'))
            ->assertSessionHas('error', 'This user is not suspended.');

        $this->assertSame(UserStatus::Active, $user->fresh()->status);
    }

    public function test_deleting_a_user_removes_everything_they_own_and_keeps_everyone_elses(): void
    {
        $user = User::factory()->create();
        $listing = CarListing::factory()->approved()->for($user, 'owner')->create();
        Storage::disk('public')->put("listings/{$listing->id}/photo.webp", 'x');
        $other = CarListing::factory()->approved()->create();
        Storage::disk('public')->put("listings/{$other->id}/photo.webp", 'x');
        $user->bookmarks()->attach($other->id);
        $bystander = User::factory()->create();
        $bystander->bookmarks()->attach([$listing->id, $other->id]);
        $buying = Conversation::factory()->for($user, 'sender')->for($other, 'listing')->create();
        Message::factory()->for($buying)->create(['sender_id' => $user->id]);
        $selling = Conversation::factory()->for($listing, 'listing')->create();
        $kept = Conversation::factory()->for($other, 'listing')->create();
        $this->insertSessionRow($user);
        $keptSession = $this->insertSessionRow($bystander);

        $this->actingAs($this->admin)
            ->delete(route('admin.users.destroy', [$user, 'search' => 'x']))
            ->assertRedirect(route('admin.users', ['search' => 'x']))
            ->assertSessionHas('success', 'User account has been deleted.');

        $this->assertModelMissing($user);
        $this->assertModelMissing($listing);
        $this->assertFalse(Storage::disk('public')->directoryExists("listings/{$listing->id}"));
        Storage::disk('public')->assertExists("listings/{$other->id}/photo.webp");
        $this->assertSame([[$bystander->id, $other->id]], DB::table('user_bookmarks')->get(['user_id', 'car_listing_id'])->map(fn ($row): array => [$row->user_id, $row->car_listing_id])->all());
        $this->assertModelMissing($buying);
        $this->assertModelMissing($selling);
        $this->assertModelExists($kept);
        $this->assertSame(0, Message::query()->count());
        $this->assertSame([$keptSession], $this->sessionRowIds());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function writes(): array
    {
        return [
            'suspend' => ['admin.users.suspend'],
            'unsuspend' => ['admin.users.unsuspend'],
            'destroy' => ['admin.users.destroy'],
        ];
    }

    #[DataProvider('writes')]
    public function test_a_write_keeps_only_a_valid_search_and_page_and_ignores_the_referer(string $route): void
    {
        $sanitised = $this->send($route, ['search' => '  seller  ', 'page' => '2', 'status' => 'pending', 'evil' => 'x']);
        $dropped = $this->send($route, ['search' => str_repeat('a', 101), 'page' => '0']);
        $arrays = $this->send($route, ['search' => ['a'], 'page' => 'two']);

        $sanitised->assertRedirect(route('admin.users', ['search' => 'seller', 'page' => 2]));
        $dropped->assertRedirect(route('admin.users'));
        $arrays->assertRedirect(route('admin.users'));
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function send(string $route, array $query): TestResponse
    {
        $target = $route === 'admin.users.unsuspend' ? User::factory()->suspended()->create() : User::factory()->create();

        $response = $this->actingAs($this->admin)->call(
            $route === 'admin.users.destroy' ? 'DELETE' : 'PATCH',
            route($route, [$target, ...$query]),
            ['reason' => self::REASON, 'duration' => '7_days'],
            server: $this->transformHeadersToServerVars(['Referer' => 'https://evil.example/']),
        );

        $response->assertSessionHas('success');

        return $response;
    }

    /**
     * @return array{status: string, suspended_at: ?string, suspended_until: ?string, suspension_reason: ?string}
     */
    private function columns(User $user): array
    {
        return (array) DB::table('users')->where('id', $user->id)->first(['status', 'suspended_at', 'suspended_until', 'suspension_reason']);
    }
}
