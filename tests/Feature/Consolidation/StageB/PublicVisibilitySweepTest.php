<?php

namespace Tests\Feature\Consolidation\StageB;

use App\Models\CarListing;
use App\Models\User;
use App\Services\CompareService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Routing\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\ApplicationRoutes;
use Tests\Support\RouteBand;
use Tests\Support\RouteParameterMap;
use Tests\TestCase;

class PublicVisibilitySweepTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const array LISTING_PAGES = ['home', 'listings.index', 'listings.advanced', 'listings.show', 'compare.show'];

    private const array PAGES_WITHOUT_LISTINGS = ['legal.privacy', 'legal.terms', 'legal.cookies'];

    private const array MARKERS = [
        'pending' => 'Sweep Marker Pending',
        'rejected' => 'Sweep Marker Rejected',
        'suspended owner' => 'Sweep Marker Suspended',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_the_sweep_covers_every_listing_page_and_names_the_pages_that_pass_trivially(): void
    {
        $swept = array_keys($this->publicGetRoutes());

        $this->assertNotEmpty($swept);

        foreach ([...self::LISTING_PAGES, ...self::PAGES_WITHOUT_LISTINGS] as $name) {
            $this->assertContains($name, $swept);
        }
    }

    /**
     * @return array<string, array{string}>
     */
    public static function actors(): array
    {
        return [
            'guest' => ['guest'],
            'the markers\' active owner' => ['owner'],
            'admin' => ['admin'],
        ];
    }

    #[DataProvider('actors')]
    public function test_no_public_page_shows_a_non_public_listing(string $actor): void
    {
        CarListing::factory()->approved()->count(3)->create();
        $owner = User::factory()->create();
        $ahead = now()->addDay();
        $markerIds = [
            CarListing::factory()->for($owner, 'owner')->create(['name' => self::MARKERS['pending'], 'created_at' => $ahead])->id,
            CarListing::factory()->rejected()->for($owner, 'owner')->create(['name' => self::MARKERS['rejected'], 'created_at' => $ahead])->id,
            CarListing::factory()->approved()->for(User::factory()->suspended(), 'owner')->create(['name' => self::MARKERS['suspended owner'], 'created_at' => $ahead])->id,
        ];

        match ($actor) {
            'guest' => null,
            'owner' => $this->actingAs($owner),
            'admin' => $this->actingAs(User::factory()->admin()->create()),
        };
        $leaks = [];

        foreach ($this->publicGetRoutes() as $name => $route) {
            $this->withSession([CompareService::SESSION_KEY => $markerIds]);
            $this->assertSame($markerIds, $this->app['session.store']->get(CompareService::SESSION_KEY));

            $response = $this->get(route($name, RouteParameterMap::parametersFor($route)));

            if ($response->status() !== 200) {
                $leaks[$name] = "status {$response->status()}";

                continue;
            }

            foreach (self::MARKERS as $marker) {
                if (str_contains((string) $response->getContent(), $marker)) {
                    $leaks[$name] = $marker;
                }
            }
        }

        $this->assertSame([], $leaks);
    }

    /**
     * @return array<string, Route>
     */
    private function publicGetRoutes(): array
    {
        return array_filter(
            ApplicationRoutes::named(),
            fn (Route $route): bool => in_array('GET', $route->methods(), true) && RouteBand::fromRoute($route) === RouteBand::Public,
        );
    }
}
