<?php

namespace Tests\Feature\Consolidation\StageC;

use App\Models\CarListing;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\Support\ApplicationRoutes;
use Tests\Support\RouteBand;
use Tests\Support\RouteParameterMap;
use Tests\TestCase;

class SignedInVisibilitySweepTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const string MARKERS_OWNER_PAGE = 'users.listings';

    private const array LISTING_PAGES = ['bookmarks.index', 'users.listings'];

    private const array GUARDED_PAGES = ['profile', 'listings.create', 'listings.edit', 'listings.images.edit', 'conversations.create', 'conversations.show', 'conversations.index'];

    private const string CONTROL = 'Sweep Control Public';

    private const array MARKERS = [
        'pending' => 'Sweep Marker Pending',
        'rejected' => 'Sweep Marker Rejected',
        'suspended owner' => 'Sweep Marker Suspended',
    ];

    private User $actor;

    private User $markersOwner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Storage::fake('public');

        $this->markersOwner = User::factory()->create();
        $ahead = now()->addDay();
        $control = CarListing::factory()->approved()->for($this->markersOwner, 'owner')->create(['name' => self::CONTROL]);
        $markers = [
            CarListing::factory()->for($this->markersOwner, 'owner')->create(['name' => self::MARKERS['pending'], 'created_at' => $ahead]),
            CarListing::factory()->rejected()->for($this->markersOwner, 'owner')->create(['name' => self::MARKERS['rejected'], 'created_at' => $ahead]),
            CarListing::factory()->approved()->for(User::factory()->suspended(), 'owner')->create(['name' => self::MARKERS['suspended owner'], 'created_at' => $ahead]),
        ];

        $this->actor = User::factory()->create();
        $this->actor->bookmarks()->attach([$control->id, ...array_map(fn (CarListing $marker): int => $marker->id, $markers)]);
    }

    public function test_the_sweep_covers_both_listing_pages_and_they_show_the_control_listing(): void
    {
        $swept = $this->signedInGetRoutes();

        foreach (self::LISTING_PAGES as $name) {
            $this->assertArrayHasKey($name, $swept);

            $this->visit($name, $swept[$name])
                ->assertOk()
                ->assertSee(self::CONTROL);
        }
    }

    public function test_the_sweep_covers_the_guarded_pages_and_each_answers_200(): void
    {
        $swept = $this->signedInGetRoutes();

        foreach (self::GUARDED_PAGES as $name) {
            $this->assertArrayHasKey($name, $swept);

            $this->visit($name, $swept[$name])->assertOk();
        }
    }

    public function test_no_signed_in_page_shows_a_non_public_listing_to_a_stranger(): void
    {
        $leaks = [];

        foreach ($this->signedInGetRoutes() as $name => $route) {
            $response = $this->visit($name, $route);

            if (! in_array($response->status(), [200, 403, 404], true)) {
                $leaks[$name] = "status {$response->status()}";

                continue;
            }

            if (in_array($name, self::LISTING_PAGES, true) && $response->status() !== 200) {
                $leaks[$name] = "status {$response->status()}";
            }

            foreach (self::MARKERS as $marker) {
                if (str_contains((string) $response->getContent(), $marker)) {
                    $leaks[$name] = $marker;
                }
            }
        }

        $this->assertSame([], $leaks);
    }

    private function visit(string $name, Route $route): TestResponse
    {
        $this->actingAs($this->actor);
        $parameters = RouteParameterMap::parametersFor($route);

        if ($name === self::MARKERS_OWNER_PAGE) {
            $parameters['user'] = (string) $this->markersOwner->id;
        }

        return $this->get(route($name, $parameters));
    }

    /**
     * @return array<string, Route>
     */
    private function signedInGetRoutes(): array
    {
        return array_filter(
            ApplicationRoutes::named(),
            fn (Route $route): bool => in_array('GET', $route->methods(), true)
                && in_array(RouteBand::fromRoute($route), [RouteBand::Auth, RouteBand::Profile, RouteBand::Seller, RouteBand::Messaging], true),
        );
    }
}
