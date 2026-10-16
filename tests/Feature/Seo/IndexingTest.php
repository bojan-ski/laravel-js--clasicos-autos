<?php

namespace Tests\Feature\Seo;

use App\Models\CarListing;
use App\Models\CarMaker;
use App\Models\Conversation;
use App\Models\User;
use App\Services\CompareService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\ApplicationRoutes;
use Tests\Support\RouteBand;
use Tests\Support\RouteParameterMap;
use Tests\TestCase;

class IndexingTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const array INDEXABLE_ROUTES = ['home', 'listings.index', 'listings.show', 'legal.privacy', 'legal.terms', 'legal.cookies'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Storage::fake('public');
    }

    /**
     * @return array<string, array{bool}>
     */
    public static function switchStates(): array
    {
        return [
            'switch off' => [false],
            'switch on' => [true],
        ];
    }

    public function test_the_switch_is_off_by_default(): void
    {
        $this->assertFalse(config('seo.indexable'));

        $this->get(route('home'))->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    #[DataProvider('switchStates')]
    public function test_every_named_get_route_carries_the_same_directive_in_header_and_meta(bool $indexable): void
    {
        config(['seo.indexable' => $indexable]);
        $appUser = User::factory()->create();
        $admin = User::factory()->admin()->create();

        $expected = [];
        $actual = [];

        foreach ($this->getRoutes() as $name => $route) {
            $response = $this->requestAsBandActor($route, $appUser, $admin);
            $directive = $this->directiveFor($indexable, $name, $response->status());

            $expected[$name] = $this->describe($directive, $this->isHtmlPage($response) ? $directive : 'no page');
            $actual[$name] = $this->describe($response->headers->get('X-Robots-Tag'), $this->isHtmlPage($response) ? $this->robotsMeta($response) : 'no page');

            if (in_array($name, self::INDEXABLE_ROUTES, true)) {
                $this->assertSame(200, $response->status(), "[{$name}] should answer 200.");
            }
        }

        $this->assertSame($expected, $actual);
        $this->assertCount($this->registeredGetRouteCount(), $actual);
    }

    /**
     * @return array<string, array{bool, string}>
     */
    public static function nonPageResponses(): array
    {
        $cases = [];

        foreach (['unknown url', 'hidden listing', 'guest profile', 'invalid filter', 'expired session', 'app user on admin', 'json message send', 'json compare full', 'server error'] as $case) {
            $cases["{$case}, switch off"] = [false, $case];
            $cases["{$case}, switch on"] = [true, $case];
        }

        return $cases;
    }

    #[DataProvider('nonPageResponses')]
    public function test_responses_that_are_not_indexable_pages_carry_the_header(bool $indexable, string $case): void
    {
        config(['seo.indexable' => $indexable]);
        $directive = $indexable ? 'noindex' : 'noindex, nofollow';

        [$response, $status] = match ($case) {
            'unknown url' => [$this->get('/no-such-page'), 404],
            'hidden listing' => [$this->get(route('listings.show', CarListing::factory()->create())), 404],
            'guest profile' => [$this->get(route('profile')), 302],
            'invalid filter' => [$this->get(route('listings.index', ['maker' => ['999999']])), 302],
            'expired session' => [$this->getExpiredSession(), 302],
            'app user on admin' => [$this->actingAs(User::factory()->create())->get(route('admin.users')), 403],
            'json message send' => [$this->sendJsonMessage(), 201],
            'json compare full' => [$this->addThirdToCompare(), 409],
            'server error' => [$this->getServerError(), 500],
        };

        $response->assertStatus($status);
        $response->assertHeader('X-Robots-Tag', $directive);

        if ($this->isHtmlPage($response)) {
            $this->assertSame($directive, $this->robotsMeta($response));
        }
    }

    #[DataProvider('switchStates')]
    public function test_error_pages_render_the_robots_meta_matching_their_header(bool $indexable): void
    {
        config(['seo.indexable' => $indexable]);
        Route::middleware('web')->get('/_test/forbidden', fn () => abort(403));

        foreach ([$this->get('/_test/forbidden'), $this->get('/no-such-page'), $this->getServerError()] as $response) {
            $this->assertTrue($this->isHtmlPage($response));
            $this->assertSame($response->headers->get('X-Robots-Tag'), $this->robotsMeta($response));
        }
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function privateListingViews(): array
    {
        return [
            'owner, pending' => ['owner', 'pending'],
            'owner, rejected' => ['owner', 'rejected'],
            'admin, pending' => ['admin', 'pending'],
            'admin, rejected' => ['admin', 'rejected'],
            'admin, suspended owner' => ['admin', 'suspended owner'],
        ];
    }

    #[DataProvider('privateListingViews')]
    public function test_an_owner_or_admin_view_of_a_non_public_listing_is_not_indexable(string $viewer, string $state): void
    {
        config(['seo.indexable' => true]);
        $listing = match ($state) {
            'pending' => CarListing::factory()->create(),
            'rejected' => CarListing::factory()->rejected()->create(),
            'suspended owner' => CarListing::factory()->approved()->for(User::factory()->suspended(), 'owner')->create(),
        };
        $user = $viewer === 'owner' ? $listing->owner : User::factory()->admin()->create();

        $response = $this->actingAs($user)->get(route('listings.show', $listing));

        $response->assertOk();
        $response->assertHeader('X-Robots-Tag', 'noindex');
        $this->assertSame('noindex', $this->robotsMeta($response));
        $response->assertDontSee('rel="canonical"', false);
        $response->assertDontSee('property="og:url"', false);
        $response->assertDontSee('application/ld+json', false);
    }

    public function test_a_filtered_listings_index_stays_indexable_with_the_bare_canonical(): void
    {
        config(['seo.indexable' => true]);
        $maker = CarMaker::factory()->create();

        $response = $this->get(route('listings.index', ['search' => 'bel', 'maker' => [$maker->id], 'page' => 2]));

        $response->assertOk();
        $response->assertHeaderMissing('X-Robots-Tag');
        $this->assertNull($this->robotsMeta($response));
        $response->assertSee('<link rel="canonical" href="'.rtrim(config('app.url'), '/').'/listings">', false);
    }

    /**
     * @return array<string, RoutingRoute>
     */
    private function getRoutes(): array
    {
        return array_filter(ApplicationRoutes::named(), fn (RoutingRoute $route): bool => in_array('GET', $route->methods(), true));
    }

    private function registeredGetRouteCount(): int
    {
        return count(array_filter(
            Route::getRoutes()->getRoutesByMethod()['GET'],
            fn (RoutingRoute $route): bool => $route->getName() !== null && ! Str::startsWith($route->getName(), 'storage.'),
        ));
    }

    private function requestAsBandActor(RoutingRoute $route, User $appUser, User $admin): TestResponse
    {
        Auth::forgetGuards();

        match (RouteBand::fromRoute($route)) {
            RouteBand::Public, RouteBand::Guest => null,
            RouteBand::Admin => $this->actingAs($admin),
            default => $this->actingAs($appUser),
        };

        return $this->get(route((string) $route->getName(), RouteParameterMap::parametersFor($route)));
    }

    private function directiveFor(bool $indexable, string $name, int $status): ?string
    {
        if (! $indexable) {
            return 'noindex, nofollow';
        }

        return in_array($name, self::INDEXABLE_ROUTES, true) && $status === 200 ? null : 'noindex';
    }

    private function isHtmlPage(TestResponse $response): bool
    {
        return ! $response->isRedirect()
            && str_starts_with((string) $response->headers->get('Content-Type'), 'text/html')
            && str_contains((string) $response->getContent(), '<head>');
    }

    private function robotsMeta(TestResponse $response): ?string
    {
        $count = preg_match_all('/<meta name="robots" content="([^"]*)">/', (string) $response->getContent(), $matches);

        $this->assertLessThanOrEqual(1, $count, 'More than one robots meta.');

        return $matches[1][0] ?? null;
    }

    private function describe(?string $header, ?string $meta): string
    {
        return 'header: '.($header ?? 'none').' | meta: '.($meta ?? 'none');
    }

    private function getExpiredSession(): TestResponse
    {
        Route::middleware('web')->get('/_test/expired', fn () => abort(419));

        return $this->get('/_test/expired');
    }

    private function getServerError(): TestResponse
    {
        config(['app.debug' => false]);
        Route::middleware('web')->get('/_test/boom', fn () => throw new RuntimeException('boom'));

        return $this->get('/_test/boom');
    }

    private function sendJsonMessage(): TestResponse
    {
        $sender = User::factory()->create();
        $conversation = Conversation::factory()->for($sender, 'sender')->create();

        return $this->actingAs($sender)->postJson(route('conversations.messages.store', $conversation), ['message' => 'Is it still for sale?']);
    }

    private function addThirdToCompare(): TestResponse
    {
        $selected = CarListing::factory()->approved()->count(2)->create()->modelKeys();

        return $this->withSession([CompareService::SESSION_KEY => $selected])
            ->postJson(route('compare.add', CarListing::factory()->approved()->create()));
    }
}
