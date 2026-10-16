<?php

namespace Tests\Feature\Consolidation\StageA;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\ApplicationRoutes;
use Tests\Support\RouteBand;
use Tests\Support\RouteParameterMap;
use Tests\TestCase;

class RouteAccessMatrixTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const string REACHES = 'reaches';

    private const string FORBIDDEN = '403 page';

    private const string UNAUTHORIZED = '401 json';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Storage::fake('public');
    }

    public function test_every_named_application_route_has_a_band_and_its_parameters(): void
    {
        $routes = ApplicationRoutes::named();

        $this->assertCount(73, $routes);

        $bands = [];

        foreach ($routes as $name => $route) {
            $bands[$name] = RouteBand::fromRoute($route)->value;
            RouteParameterMap::parametersFor($route);
        }

        $census = array_count_values($bands);
        ksort($census);

        $this->assertSame(['admin' => 24, 'auth' => 16, 'guest' => 8, 'messaging' => 4, 'profile' => 5, 'public' => 14, 'seller' => 2], $census);
        $this->assertSame([], array_filter($bands, fn (string $band, string $name): bool => Str::startsWith($name, 'admin.') !== ($band === RouteBand::Admin->value), ARRAY_FILTER_USE_BOTH));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function actors(): array
    {
        return [
            'guest' => ['guest'],
            'active app user' => ['appUser'],
            'suspended app user' => ['suspended'],
            'admin' => ['admin'],
        ];
    }

    #[DataProvider('actors')]
    public function test_each_route_gives_the_actor_its_band_outcome(string $actor): void
    {
        $user = match ($actor) {
            'guest' => null,
            'appUser' => User::factory()->create(),
            'suspended' => User::factory()->suspended()->create(),
            'admin' => User::factory()->admin()->create(),
        };

        $expected = [];
        $actual = [];

        foreach (ApplicationRoutes::named() as $name => $route) {
            $band = RouteBand::fromRoute($route);
            $outcome = $this->expectedOutcome($route, $band, $user);
            $response = $this->requestRoute($route, $band, $user);

            $expected[$name] = $outcome;
            $actual[$name] = $this->satisfies($response, $outcome, $route, $band) ? $outcome : $this->describe($response);
        }

        $this->assertSame($expected, $actual);
    }

    public function test_a_route_whose_middleware_matches_no_band_fails_the_matrix(): void
    {
        Route::middleware(['web', 'throttle:6,1'])->get('/_test/unbanded', fn () => 'x')->name('test.unbanded');
        Route::getRoutes()->refreshNameLookups();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('[test.unbanded]');

        foreach (ApplicationRoutes::named() as $route) {
            RouteBand::fromRoute($route);
        }
    }

    public function test_an_unmapped_parameterised_route_fails_the_matrix(): void
    {
        Route::middleware('web')->get('/_test/things/{thing}', fn () => 'x')->name('test.thing');
        Route::getRoutes()->refreshNameLookups();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('[test.thing]');

        foreach (ApplicationRoutes::named() as $route) {
            RouteParameterMap::parametersFor($route);
        }
    }

    private function expectedOutcome(RoutingRoute $route, RouteBand $band, ?User $user): string
    {
        if ($user === null) {
            return match ($band) {
                RouteBand::Public, RouteBand::Guest => self::REACHES,
                default => '→ login',
            };
        }

        if ($user->isSuspended()) {
            return $band === RouteBand::Public && $route->methods()[0] !== 'GET' ? self::UNAUTHORIZED : '→ login';
        }

        return match ($band) {
            RouteBand::Public, RouteBand::Auth => self::REACHES,
            RouteBand::Guest => '→ '.$user->landingRoute(),
            RouteBand::Profile => $user->isAppUser() ? self::REACHES : self::FORBIDDEN,
            RouteBand::Seller, RouteBand::Messaging => $user->isAppUser() ? self::REACHES : self::FORBIDDEN,
            RouteBand::Admin => $user->isAdmin() ? self::REACHES : self::FORBIDDEN,
        };
    }

    private function requestRoute(RoutingRoute $route, RouteBand $band, ?User $user): TestResponse
    {
        if ($user !== null) {
            $this->actingAs($user);
        }

        $method = $route->methods()[0];
        $uri = route((string) $route->getName(), RouteParameterMap::parametersFor($route));

        if ($band === RouteBand::Public && $method !== 'GET') {
            return $this->json($method, $uri);
        }

        return $this->call($method, $uri);
    }

    private function satisfies(TestResponse $response, string $outcome, RoutingRoute $route, RouteBand $band): bool
    {
        if ($outcome === self::UNAUTHORIZED) {
            return $response->status() === 401;
        }

        if ($outcome === self::FORBIDDEN) {
            return $response->status() === 403 && str_contains((string) $response->getContent(), e(__('app.errors.403.title')));
        }

        if (str_starts_with($outcome, '→ ')) {
            return $response->isRedirect(route(Str::after($outcome, '→ ')));
        }

        if ($route->methods()[0] === 'GET') {
            return $response->status() === 200;
        }

        if ($band === RouteBand::Public) {
            return $response->isSuccessful() || ($response->isRedirect() && ! $response->isRedirect(route('login')));
        }

        return $response->status() < 500
            && $response->status() !== 403
            && ($band === RouteBand::Guest || ! $response->isRedirect(route('login')));
    }

    private function describe(TestResponse $response): string
    {
        return $response->status().($response->isRedirect() ? ' → '.$response->headers->get('Location') : '');
    }
}
