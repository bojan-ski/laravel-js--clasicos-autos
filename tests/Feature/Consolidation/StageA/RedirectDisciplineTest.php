<?php

namespace Tests\Feature\Consolidation\StageA;

use App\Models\CarListing;
use App\Models\User;
use App\Support\ListingFormOptions;
use App\Support\ListingFormValues;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
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

                if ($response->getSession()?->has('errors') || $response->getSession()?->has('error')) {
                    $rejectedValidPayloads[] = $name;
                }
            }
        }

        $offenders = array_filter($locations, fn (?string $location): bool => $location !== null && parse_url($location, PHP_URL_HOST) === 'evil.example');

        $this->assertArrayHasKey('cookie-consent.store (empty)', $locations);
        $this->assertSame([], $rejectedValidPayloads);
        $this->assertSame([], $offenders);
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
            RouteBand::Auth, RouteBand::Profile, RouteBand::Seller => User::factory()->create(),
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
            default => [],
        };
    }
}
