<?php

namespace Tests\Feature\Consolidation\StageB;

use App\Enums\ListingStatus;
use App\Models\CarListing;
use App\Models\CarMaker;
use App\Models\CarModel;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class StageBExitConditionTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const string FRAGMENT = 'Postal Bel Air';

    private const string REASON = 'Rusted sills under the doors.';

    private const array NAMES = [
        'public' => 'Postal Bel Air Azul',
        'pending' => 'Postal Bel Air Pendiente',
        'rejected' => 'Postal Bel Air Rechazado',
        'suspended owner' => 'Postal Bel Air Suspendido',
    ];

    private CarMaker $maker;

    /**
     * @var array<string, CarListing>
     */
    private array $listings;

    private User $owner;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        $this->maker = CarMaker::factory()->create(['name' => 'Chevrolet']);
        $carModel = CarModel::factory()->for($this->maker, 'maker')->create(['name' => 'Bel Air', 'year_from' => 1950, 'year_to' => 1975]);
        $this->owner = User::factory()->create();
        $this->admin = User::factory()->admin()->create();

        $listing = fn (): mixed => CarListing::factory()->forCarModel($carModel);
        $this->listings = [
            'public' => $listing()->approved()->for($this->owner, 'owner')->create(['name' => self::NAMES['public'], 'created_at' => now()->subDay()]),
            'pending' => $listing()->for($this->owner, 'owner')->create(['name' => self::NAMES['pending']]),
            'rejected' => $listing()->rejected(self::REASON)->for($this->owner, 'owner')->create(['name' => self::NAMES['rejected']]),
            'suspended owner' => $listing()->approved()->for(User::factory()->suspended(), 'owner')->create(['name' => self::NAMES['suspended owner']]),
        ];
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function listingPagesAndViewers(): array
    {
        $cases = [];

        foreach (['index', 'search', 'maker chip', 'advanced search', 'home'] as $page) {
            foreach (['guest', 'owner', 'admin'] as $viewer) {
                $cases["{$page} as {$viewer}"] = [$page, $viewer];
            }
        }

        return $cases;
    }

    #[DataProvider('listingPagesAndViewers')]
    public function test_only_the_public_listing_is_browsable_on_every_listing_page(string $page, string $viewer): void
    {
        $this->actAs($viewer);

        $response = $this->get(match ($page) {
            'index' => route('listings.index'),
            'search' => route('listings.index', ['search' => self::FRAGMENT]),
            'maker chip' => route('listings.index', ['maker' => $this->maker->id]),
            'advanced search' => route('listings.advanced', ['car_maker' => $this->maker->id]),
            'home' => route('home'),
        });

        $response->assertOk()->assertSee(self::NAMES['public']);

        foreach (['pending', 'rejected', 'suspended owner'] as $hidden) {
            $response->assertDontSee(self::NAMES[$hidden]);
        }
    }

    /**
     * @return array<string, array{string, string, string|null}>
     */
    public static function detailOutcomes(): array
    {
        $table = [
            'public' => ['guest' => 'no panel', 'stranger' => 'no panel', 'owner' => 'approved', 'admin' => 'approved'],
            'pending' => ['guest' => '404', 'stranger' => '404', 'owner' => 'pending', 'admin' => 'pending'],
            'rejected' => ['guest' => '404', 'stranger' => '404', 'owner' => 'rejected', 'admin' => 'rejected'],
            'suspended owner' => ['guest' => '404', 'stranger' => '404', 'admin' => 'approved'],
        ];

        $cases = [];

        foreach ($table as $listing => $outcomes) {
            foreach ($outcomes as $viewer => $outcome) {
                $cases["{$listing} as {$viewer}"] = [$listing, $viewer, $outcome];
            }
        }

        return $cases;
    }

    #[DataProvider('detailOutcomes')]
    public function test_the_detail_page_answers_each_viewer_per_listing_state(string $listing, string $viewer, string $outcome): void
    {
        $this->actAs($viewer);

        $response = $this->get(route('listings.show', $this->listings[$listing]));

        if ($outcome === '404') {
            $response->assertNotFound()
                ->assertSee(__('app.errors.404.title'))
                ->assertDontSee(self::NAMES[$listing]);

            return;
        }

        $response->assertOk()->assertSee(self::NAMES[$listing]);

        if ($outcome === 'no panel') {
            $response->assertDontSee(__('app.listings.show.status.label'));

            return;
        }

        $response->assertSeeInOrder([__('app.listings.show.status.label'), ListingStatus::from($outcome)->label()]);

        if ($outcome === 'rejected') {
            $response->assertSeeInOrder([__('app.listings.show.status.reason'), self::REASON]);
        }

        if ($listing === 'suspended owner') {
            $response->assertSee(__('app.listings.show.status.owner_suspended'));
        } else {
            $response->assertDontSee(__('app.listings.show.status.owner_suspended'));
        }
    }

    private function actAs(string $viewer): void
    {
        match ($viewer) {
            'guest' => null,
            'owner' => $this->actingAs($this->owner),
            'stranger' => $this->actingAs(User::factory()->create()),
            'admin' => $this->actingAs($this->admin),
        };
    }
}
