<?php

namespace Tests\Feature\Consolidation\StageA;

use App\Models\CarListing;
use App\Models\Conversation;
use App\Models\User;
use App\Support\ListingFormOptions;
use App\Support\ListingFormValues;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Support\RouteBand;
use Tests\Support\RouteParameterMap;
use Tests\TestCase;

class RedirectDisciplineTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const string HOSTILE_REFERER = 'https://evil.example/';

    private const array STAGE_D_PINNED_TARGETS = [
        'listings.store' => 'listings.create',
        'listings.update' => 'listings.edit',
        'listings.images.store' => 'listings.images.edit',
        'admin.listings.approve' => 'listings.show',
        'admin.listings.reject' => 'listings.show',
    ];

    private const array STAGE_E_PINNED_TARGETS = [
        'conversations.store' => 'conversations.create',
        'conversations.messages.store' => 'conversations.show',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Notification::fake();
        Storage::fake('public');
    }

    public function test_no_write_follows_a_hostile_referer(): void
    {
        $writes = $this->writeRoutes();
        $locations = [];
        $rejectedValidPayloads = [];

        $this->assertNotEmpty($writes);

        foreach ($writes as $name => $route) {
            $locations["{$name} (empty)"] = $this->send($route, fn (?User $user): array => [])->headers->get('Location');

            if ($this->hasValidPayload($name)) {
                $response = $this->send($route, fn (?User $user): array => $this->validPayload($name, $user));
                $locations["{$name} (valid)"] = $response->headers->get('Location');

                $session = $response->baseResponse instanceof RedirectResponse ? $response->getSession() : null;

                if ($session?->has('errors') || $session?->has('error')) {
                    $rejectedValidPayloads[] = $name;
                }
            }
        }

        $offenders = array_filter($locations, fn (?string $location): bool => $location !== null && parse_url($location, PHP_URL_HOST) === 'evil.example');

        $this->assertArrayHasKey('cookie-consent.store (empty)', $locations);
        $this->assertSame([], $rejectedValidPayloads);
        $this->assertSame([], $offenders);
        $this->assertArrayHasKey('conversations.read (valid)', $locations);
        $this->assertNull($locations['conversations.read (valid)']);
    }

    public function test_empty_stage_d_writes_land_on_their_pinned_targets(): void
    {
        $writes = $this->writeRoutes();
        $landed = [];

        foreach (self::STAGE_D_PINNED_TARGETS as $name => $target) {
            $location = $this->send($writes[$name], fn (?User $user): array => [])->headers->get('Location');
            $landed[$name] = $location === route($target, $target === 'listings.create' ? [] : CarListing::query()->latest('id')->value('id'));
        }

        $this->assertSame(array_fill_keys(array_keys(self::STAGE_D_PINNED_TARGETS), true), $landed);
    }

    public function test_empty_stage_e_writes_land_on_their_pinned_targets(): void
    {
        $writes = $this->writeRoutes();
        $landed = [];

        foreach (self::STAGE_E_PINNED_TARGETS as $name => $target) {
            $location = $this->send($writes[$name], fn (?User $user): array => [])->headers->get('Location');
            $parameter = $target === 'conversations.create'
                ? CarListing::query()->latest('id')->value('id')
                : Conversation::query()->latest('id')->value('id');
            $landed[$name] = $location === route($target, $parameter);
        }

        $this->assertSame(array_fill_keys(array_keys(self::STAGE_E_PINNED_TARGETS), true), $landed);
    }

    /**
     * @return array<string, RoutingRoute>
     */
    private function writeRoutes(): array
    {
        $routes = array_filter(
            Route::getRoutes()->getRoutesByName(),
            fn (RoutingRoute $route, string $name): bool => ! Str::startsWith($name, 'storage.')
                && array_intersect($route->methods(), ['POST', 'PUT', 'PATCH', 'DELETE']) !== [],
            ARRAY_FILTER_USE_BOTH,
        );
        ksort($routes);

        return $routes;
    }

    /**
     * @param  callable(?User): array<string, mixed>  $payload
     */
    private function send(RoutingRoute $route, callable $payload): TestResponse
    {
        $user = match (RouteBand::fromRoute($route)) {
            RouteBand::Public, RouteBand::Guest => null,
            RouteBand::Auth, RouteBand::Profile, RouteBand::Seller, RouteBand::Messaging => User::factory()->create(),
            RouteBand::Admin => User::factory()->admin()->create(),
        };

        if ($user !== null) {
            $this->actingAs($user);
        }

        $response = $this->call(
            $route->methods()[0],
            route((string) $route->getName(), RouteParameterMap::parametersFor($route)),
            $payload($user),
            server: $this->transformHeadersToServerVars(['Referer' => self::HOSTILE_REFERER]),
        );

        $this->app['auth']->forgetGuards();

        return $response;
    }

    private function hasValidPayload(string $name): bool
    {
        return in_array($name, [
            'bookmarks.destroy',
            'bookmarks.store',
            'compare.add',
            'compare.clear',
            'compare.remove',
            'cookie-consent.store',
            'listings.destroy',
            'listings.images.destroy',
            'listings.images.primary',
            'listings.update',
            'register.store',
            'login.store',
            'logout',
            'password.email',
            'password.update',
            'profile.password.update',
            'profile.destroy',
            'admin.account.password.update',
            'admin.listings.approve',
            'admin.listings.reject',
            'conversations.read',
            'conversations.store',
            'conversations.messages.store',
            'conversations.messages.destroy',
        ], true);
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(string $name, ?User $user): array
    {
        $existing = fn (): User => User::factory()->create();

        return match ($name) {
            'register.store' => [
                'username' => 'new seller',
                'email' => 'new.seller@example.com',
                'phone_number' => '+5355512345',
                'password' => 'secret1',
                'password_confirmation' => 'secret1',
                'terms_and_conditions' => '1',
                'privacy_policy' => '1',
            ],
            'login.store' => ['email' => $existing()->email, 'password' => 'password'],
            'password.email' => ['email' => $existing()->email],
            'password.update' => (function () use ($existing): array {
                $target = $existing();

                return [
                    'token' => Password::broker()->createToken($target),
                    'email' => $target->email,
                    'password' => 'secret12',
                    'password_confirmation' => 'secret12',
                ];
            })(),
            'profile.password.update', 'admin.account.password.update' => [
                'old_password' => 'password',
                'password' => 'secret12',
                'password_confirmation' => 'secret12',
            ],
            'profile.destroy' => ['delete_password' => 'password'],
            'listings.update' => [
                ...ListingFormValues::for($user->listings()->latest('id')->firstOrFail()),
                'car_model_id' => ListingFormOptions::OTHER_MODEL,
                'model' => 'Typed model',
            ],
            'admin.listings.approve' => ['version' => CarListing::query()->latest('id')->firstOrFail()->moderationVersion()],
            'admin.listings.reject' => [
                'version' => CarListing::query()->latest('id')->firstOrFail()->moderationVersion(),
                'rejection_reason' => 'Photos do not match the description.',
            ],
            'conversations.store', 'conversations.messages.store' => ['message' => 'Is this car still available?'],
            default => [],
        };
    }
}
