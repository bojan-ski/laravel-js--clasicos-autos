<?php

namespace Tests\Feature\Consolidation\StageG;

use App\Enums\ListingStatus;
use App\Models\CarListing;
use App\Models\CarMaker;
use App\Models\CarModel;
use App\Models\User;
use App\Services\CompareService;
use App\Support\Seo\CanonicalUrl;
use Carbon\CarbonInterface;
use Generator;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PublicQueryVisibilityTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const string TOKEN = 'Zephyr';

    private const array BRANCHES = ['name', 'model', 'maker'];

    private const array HIDDEN_KINDS = ['Pending', 'Rejected', 'Suspended'];

    private User $owner;

    private CarMaker $makerM;

    private CarMaker $makerZ;

    private CarModel $modelM;

    private CarModel $modelZ;

    /**
     * @var array<string, CarListing>
     */
    private array $controls = [];

    /**
     * @var array<string, CarListing>
     */
    private array $markers = [];

    /**
     * @var list<CarListing>
     */
    private array $fillers = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->fixture();
    }

    /**
     * @return Generator<string, array{string, string}>
     */
    public static function probes(): Generator
    {
        $everyViewer = ['guest', 'owner', 'admin'];
        $probes = [
            'home' => $everyViewer,
            'index' => $everyViewer,
            'index_search' => $everyViewer,
            'index_makers' => $everyViewer,
            'index_bare_maker' => $everyViewer,
            'advanced_maker' => $everyViewer,
            'advanced_model' => $everyViewer,
            'advanced_years' => $everyViewer,
            'seller_page' => ['stranger'],
        ];

        foreach ($probes as $probe => $actors) {
            foreach ($actors as $actor) {
                yield "{$probe} as {$actor}" => [$probe, $actor];
            }
        }
    }

    #[DataProvider('probes')]
    public function test_each_listing_surface_shows_its_controls_and_no_marker(string $probe, string $actor): void
    {
        $this->actAs($actor);

        [$response, $shown] = match ($probe) {
            'home' => [$this->get(route('home')), $this->controls],
            'index' => [$this->get(route('listings.index')), $this->controls],
            'index_search' => [$this->get(route('listings.index', ['search' => self::TOKEN])), $this->controls],
            'index_makers' => [$this->get(route('listings.index', ['maker' => [$this->makerM->id, $this->makerZ->id]])), $this->controls],
            'index_bare_maker' => [$this->get(route('listings.index').'?maker='.$this->makerZ->id), [$this->controls['maker']]],
            'advanced_maker' => [$this->get(route('listings.advanced', ['car_maker' => $this->makerZ->id])), [$this->controls['maker']]],
            'advanced_model' => [$this->get(route('listings.advanced', ['model' => self::TOKEN])), [$this->controls['model']]],
            'advanced_years' => [$this->get(route('listings.advanced', ['year_from' => 1957, 'year_to' => 1957])), $this->controls],
            'seller_page' => [$this->get(route('users.listings', $this->owner)), $this->controls],
        };

        $response->assertOk();
        $this->assertSame([], $this->missing($response, $shown));
        $this->assertSame([], $this->leaks($response));

        if ($probe === 'advanced_years') {
            $this->assertSame(15, $response->viewData('listings')->total());
        }

        if ($probe === 'seller_page') {
            $this->assertSame(3, $response->viewData('listings')->total());
        }
    }

    public function test_the_second_index_page_holds_exactly_the_three_oldest_fillers(): void
    {
        $response = $this->get(route('listings.index', ['page' => 2]))->assertOk();

        $this->assertSame(15, $response->viewData('listings')->total());
        $this->assertSame(['Filler 03', 'Filler 02', 'Filler 01'], $response->viewData('listings')->pluck('name')->all());
        $this->assertSame([], $this->leaks($response));
    }

    public function test_the_owner_card_counts_only_the_owners_public_listings(): void
    {
        $response = $this->get(route('listings.show', $this->controls['name']))->assertOk();

        $rows = array_column($response->viewData('specs')->owner(), 'value', 'label');

        $this->assertSame('3', $rows[__('app.listings.show.owner.total_listings')]);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function compareViewers(): array
    {
        return ['guest' => ['guest'], 'owner' => ['owner'], 'admin' => ['admin']];
    }

    #[DataProvider('compareViewers')]
    public function test_compare_drops_every_marker_and_keeps_the_control(string $actor): void
    {
        $this->actAs($actor);
        $control = $this->controls['name'];
        $results = [];

        foreach ($this->markers as $label => $marker) {
            $seed = [$marker->id, $control->id];
            $this->withSession([CompareService::SESSION_KEY => $seed]);
            $this->assertSame($seed, $this->app['session.store']->get(CompareService::SESSION_KEY));

            $response = $this->get(route('compare.show'))->assertOk();

            $results[$label] = [
                'missing' => $this->missing($response, [$control], 'compare.remove'),
                'leaks' => $this->leaks($response),
                'held' => $this->app['session.store']->get(CompareService::SESSION_KEY),
            ];
        }

        $this->assertCount(9, $results);
        $this->assertSame(array_fill_keys(array_keys($this->markers), ['missing' => [], 'leaks' => [], 'held' => [$control->id]]), $results);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function bookmarkers(): array
    {
        return ['stranger' => ['stranger'], 'admin' => ['admin']];
    }

    #[DataProvider('bookmarkers')]
    public function test_the_bookmarks_page_hides_every_bookmarked_marker(string $actor): void
    {
        $user = $this->actAs($actor);
        $at = now()->subDays(5);
        $rows = [];

        foreach ([...$this->fillers, ...array_values($this->controls), ...array_values($this->markers)] as $listing) {
            $at = $at->copy()->addMinute();
            $rows[] = ['user_id' => $user->id, 'car_listing_id' => $listing->id, 'created_at' => $at, 'updated_at' => $at];
        }

        DB::table('user_bookmarks')->insert($rows);
        $this->assertSame(24, DB::table('user_bookmarks')->where('user_id', $user->id)->count());

        $first = $this->get(route('bookmarks.index'))->assertOk();
        $second = $this->get(route('bookmarks.index', ['page' => 2]))->assertOk();

        $this->assertSame(15, $first->viewData('listings')->total());
        $this->assertSame(
            [],
            array_values(array_intersect(
                $this->missing($first, [...array_values($this->controls), ...$this->fillers]),
                $this->missing($second, [...array_values($this->controls), ...$this->fillers]),
            )),
        );
        $this->assertSame([], $this->leaks($first));
        $this->assertSame([], $this->leaks($second));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function everyStatusViewers(): array
    {
        return ['owner' => ['owner'], 'admin' => ['admin']];
    }

    #[DataProvider('everyStatusViewers')]
    public function test_the_seller_page_shows_the_owner_and_admins_every_row(string $actor): void
    {
        $this->actAs($actor);
        $ownersRows = [
            ...array_values($this->controls),
            ...array_values(array_filter($this->markers, fn (CarListing $marker): bool => $marker->user_id === $this->owner->id)),
        ];
        $this->assertCount(9, $ownersRows);

        $response = $this->get(route('users.listings', $this->owner))->assertOk();

        $this->assertSame(9, $response->viewData('listings')->total());
        $this->assertSame([], $this->missing($response, $ownersRows));
    }

    public function test_the_sitemap_lists_every_public_listing_and_no_marker(): void
    {
        $response = $this->get(route('sitemap'))->assertOk();

        $content = (string) $response->getContent();
        $absent = array_values(array_filter(
            [...array_values($this->controls), ...$this->fillers],
            fn (CarListing $listing): bool => ! str_contains($content, $this->loc($listing)),
        ));

        $this->assertSame([], $absent);
        $this->assertSame([], $this->leaks($response));
    }

    private function fixture(): void
    {
        $this->owner = User::factory()->create();
        $suspended = User::factory()->suspended()->create();
        $fillerOwner = User::factory()->create();
        $this->makerM = CarMaker::factory()->create(['name' => 'Matrix Motors']);
        $this->makerZ = CarMaker::factory()->create(['name' => self::TOKEN.' Works']);
        $this->modelM = CarModel::factory()->create(['car_maker_id' => $this->makerM->id, 'name' => 'Roadster', 'year_from' => 1950, 'year_to' => 1960]);
        $this->modelZ = CarModel::factory()->create(['car_maker_id' => $this->makerZ->id, 'name' => 'Coupe', 'year_from' => 1950, 'year_to' => 1960]);

        foreach (range(1, 12) as $index) {
            $this->fillers[] = $this->listing('filler', sprintf('Filler %02d', $index), $fillerOwner, ListingStatus::Approved, now()->subDays(10)->addMinutes($index));
        }

        $this->controls = [
            'name' => $this->listing('name', self::TOKEN.' Control', $this->owner, ListingStatus::Approved, now()->subDays(2)),
            'model' => $this->listing('model', 'Control Special', $this->owner, ListingStatus::Approved, now()->subDays(2)),
            'maker' => $this->listing('maker', 'Control Works', $this->owner, ListingStatus::Approved, now()->subDays(2)),
        ];

        foreach (self::HIDDEN_KINDS as $kind) {
            [$status, $owner] = match ($kind) {
                'Pending' => [ListingStatus::Pending, $this->owner],
                'Rejected' => [ListingStatus::Rejected, $this->owner],
                'Suspended' => [ListingStatus::Approved, $suspended],
            };

            foreach (self::BRANCHES as $branch) {
                $name = match ($branch) {
                    'name' => self::TOKEN." Hidden {$kind}",
                    'model' => "Hidden {$kind} Special",
                    'maker' => "Hidden {$kind} Works",
                };

                $this->markers["{$kind} {$branch}"] = $this->listing($branch, $name, $owner, $status, now()->subDay());
            }
        }
    }

    private function listing(string $branch, string $name, User $owner, ListingStatus $status, CarbonInterface $at): CarListing
    {
        $factory = match ($branch) {
            'name', 'filler' => CarListing::factory()->forCarModel($this->modelM),
            'model' => CarListing::factory()->customModel()->state(['car_maker_id' => $this->makerM->id, 'model' => self::TOKEN.' Special']),
            'maker' => CarListing::factory()->forCarModel($this->modelZ),
        };

        $factory = match ($status) {
            ListingStatus::Approved => $factory->approved(),
            ListingStatus::Rejected => $factory->rejected(),
            ListingStatus::Pending => $factory,
        };

        return $factory->withImages()->for($owner, 'owner')->create(['name' => $name, 'year' => 1957, 'created_at' => $at, 'updated_at' => $at]);
    }

    private function actAs(string $actor): ?User
    {
        $user = match ($actor) {
            'guest' => null,
            'owner' => $this->owner,
            'admin' => User::factory()->admin()->create(),
            'stranger' => User::factory()->create(),
        };

        if ($user !== null) {
            $this->actingAs($user);
        }

        return $user;
    }

    /**
     * @param  iterable<CarListing>  $listings
     * @return list<string>
     */
    private function missing(TestResponse $response, iterable $listings, string $linkRoute = 'listings.show'): array
    {
        $content = (string) $response->getContent();
        $missing = [];

        foreach ($listings as $listing) {
            if (! str_contains($content, e($listing->name)) || ! str_contains($content, '"'.route($linkRoute, $listing).'"')) {
                $missing[] = $listing->name;
            }
        }

        return $missing;
    }

    /**
     * @return list<string>
     */
    private function leaks(TestResponse $response): array
    {
        $content = (string) $response->getContent();
        $leaks = [];

        foreach ($this->markers as $label => $marker) {
            $needles = [
                'name' => e($marker->name),
                'show' => '"'.route('listings.show', $marker).'"',
                'compare' => '"'.route('compare.add', $marker).'"',
                'bookmark' => '"'.route('bookmarks.store', $marker).'"',
                'loc' => $this->loc($marker),
                'image' => "listings/{$marker->id}/",
            ];

            foreach ($needles as $kind => $needle) {
                if (str_contains($content, $needle)) {
                    $leaks[] = "{$label} ({$kind})";
                }
            }
        }

        return $leaks;
    }

    private function loc(CarListing $listing): string
    {
        return '<loc>'.CanonicalUrl::route('listings.show', $listing).'</loc>';
    }
}
