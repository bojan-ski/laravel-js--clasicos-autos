<?php

namespace Tests\Feature\Auth;

use App\Enums\UserStatus;
use App\Http\Middleware\EndSuspendedSessions;
use App\Models\CarListing;
use App\Models\User;
use App\Support\ListingFormValues;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class EndSuspendedSessionsTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    /**
     * @return array<string, array{string}>
     */
    public static function liveSessionActions(): array
    {
        return [
            'delete own account' => ['profile.destroy'],
            'bookmark a public listing' => ['bookmarks.store'],
            'save own listing' => ['listings.update'],
            'delete own listing' => ['listings.destroy'],
            'open an unknown listing id' => ['unknown listing'],
        ];
    }

    #[DataProvider('liveSessionActions')]
    public function test_a_suspended_user_is_signed_out_with_the_notice_and_nothing_changes(string $action): void
    {
        $this->travelTo('2026-10-15 10:00:00');
        $user = User::factory()->suspended(now()->addDays(30))->create();
        $own = CarListing::factory()->approved()->for($user, 'owner')->create(['name' => 'Original name']);
        $public = CarListing::factory()->approved()->create();

        $response = match ($action) {
            'profile.destroy' => $this->actingAs($user)->delete(route('profile.destroy'), ['delete_password' => 'password']),
            'bookmarks.store' => $this->actingAs($user)->post(route('bookmarks.store', $public)),
            'listings.update' => $this->actingAs($user)->put(route('listings.update', $own), [...ListingFormValues::for($own), 'name' => 'Changed name']),
            'listings.destroy' => $this->actingAs($user)->delete(route('listings.destroy', $own)),
            'unknown listing' => $this->actingAs($user)->get(route('listings.show', 999999)),
        };

        $response->assertRedirect(route('login'))
            ->assertSessionHasErrors(['email' => 'Your account has been suspended until 14.11.2026: Repeated abusive messages.']);
        $this->assertGuest();
        $this->assertModelExists($user);
        $this->assertSame('Original name', $own->fresh()->name);
        $this->assertSame(0, DB::table('user_bookmarks')->count());
        $this->assertSame(UserStatus::Suspended, $user->fresh()->status);
    }

    public function test_the_indefinite_notice_is_used_for_an_indefinite_suspension(): void
    {
        $user = User::factory()->suspended()->create();

        $this->actingAs($user)->get(route('profile'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['email' => 'Your account has been suspended indefinitely: Repeated abusive messages.']);
    }

    public function test_a_json_request_gets_401_with_the_notice(): void
    {
        $user = User::factory()->suspended()->create();

        $this->actingAs($user)->postJson(route('compare.add', CarListing::factory()->approved()->create()))
            ->assertUnauthorized()
            ->assertExactJson(['message' => 'Your account has been suspended indefinitely: Repeated abusive messages.']);

        $this->assertGuest();
    }

    public function test_signing_out_rotates_the_remember_token(): void
    {
        $user = User::factory()->suspended()->create();
        $user->forceFill(['remember_token' => 'known-remember-token'])->save();

        $this->actingAs($user)->get(route('home'))->assertRedirect(route('login'));

        $this->assertNotSame('known-remember-token', $user->fresh()->remember_token);
    }

    public function test_an_expired_suspension_is_lifted_and_the_request_is_served(): void
    {
        $user = User::factory()->suspended(now()->subMinute())->create();

        $this->actingAs($user)->get(route('profile'))->assertOk();

        $this->assertAuthenticatedAs($user);
        $this->assertSame(UserStatus::Active, $user->fresh()->status);
        $this->assertNull($user->fresh()->suspended_until);
    }

    public function test_an_active_user_passes_without_a_query(): void
    {
        $user = User::factory()->create();
        $request = Request::create('/');
        $request->setUserResolver(fn (): User => $user);
        DB::enableQueryLog();
        DB::flushQueryLog();

        $response = $this->app->make(EndSuspendedSessions::class)->handle($request, fn (): Response => new Response('passed'));

        $this->assertSame('passed', $response->getContent());
        $this->assertSame([], DB::getQueryLog());
    }

    public function test_an_admin_and_a_guest_are_served(): void
    {
        $this->get(route('home'))->assertOk();
        $this->actingAs(User::factory()->admin()->create())->get(route('admin.users'))->assertOk();
    }
}
