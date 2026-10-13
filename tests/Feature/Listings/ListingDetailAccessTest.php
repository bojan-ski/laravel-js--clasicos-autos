<?php

namespace Tests\Feature\Listings;

use App\Models\CarListing;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ListingDetailAccessTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const array SEES_EVERY_STATE = ['owner', 'suspended owner', 'admin'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    /**
     * @return array<string, array{string, string, bool}>
     */
    public static function viewersAndStates(): array
    {
        $cases = [];

        foreach (['guest', 'owner', 'suspended owner', 'other app user', 'suspended app user', 'admin'] as $viewer) {
            foreach (['pending', 'approved', 'rejected', 'approved with a suspended owner'] as $state) {
                $visible = in_array($viewer, self::SEES_EVERY_STATE, true) || $state === 'approved';
                $cases["{$viewer} × {$state}"] = [$viewer, $state, $visible];
            }
        }

        return $cases;
    }

    #[DataProvider('viewersAndStates')]
    public function test_a_listing_renders_only_for_viewers_allowed_to_see_its_state(string $viewer, string $state, bool $visible): void
    {
        $owner = $viewer === 'suspended owner' || $state === 'approved with a suspended owner'
            ? User::factory()->suspended()->create()
            : User::factory()->create();
        $listing = $this->listingIn($state, $owner);
        $this->actAs($viewer, $owner);

        $response = $this->get(route('listings.show', $listing));

        if ($visible) {
            $response->assertViewIs('pages.listings.show');
        } else {
            $response->assertNotFound()->assertSee(__('app.errors.404.title'));
        }
    }

    public function test_an_unknown_id_is_not_found(): void
    {
        $this->get(route('listings.show', 999999))->assertNotFound();
    }

    public function test_a_non_numeric_key_matches_no_route(): void
    {
        $this->expectsDatabaseQueryCount(0);

        $this->get('/listings/abc')->assertNotFound();
    }

    public function test_the_advanced_search_path_still_reaches_the_advanced_search(): void
    {
        $this->get('/listings/advanced')->assertViewIs('pages.listings.advanced');
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function writeCheckViewers(): array
    {
        return [
            'guest on an approved listing' => ['guest', 'approved'],
            'other app user on an approved listing' => ['other app user', 'approved'],
            'owner on a pending listing' => ['owner', 'pending'],
            'admin on a rejected listing' => ['admin', 'rejected'],
        ];
    }

    #[DataProvider('writeCheckViewers')]
    public function test_viewing_a_listing_writes_nothing(string $viewer, string $state): void
    {
        $owner = User::factory()->create();
        $listing = $this->listingIn($state, $owner);
        $this->actAs($viewer, $owner);
        $writes = [];
        DB::listen(function (QueryExecuted $query) use (&$writes): void {
            if (preg_match('/^\s*(insert|update|delete|replace)\b/i', $query->sql) === 1) {
                $writes[] = $query->sql;
            }
        });

        $this->get(route('listings.show', $listing))->assertViewIs('pages.listings.show');

        $this->assertSame([], $writes);
    }

    private function listingIn(string $state, User $owner): CarListing
    {
        $factory = CarListing::factory()->for($owner, 'owner');

        return match ($state) {
            'pending' => $factory->create(),
            'rejected' => $factory->rejected()->create(),
            'approved', 'approved with a suspended owner' => $factory->approved()->create(),
        };
    }

    private function actAs(string $viewer, User $owner): void
    {
        match ($viewer) {
            'guest' => null,
            'owner', 'suspended owner' => $this->actingAs($owner),
            'other app user' => $this->actingAs(User::factory()->create()),
            'suspended app user' => $this->actingAs(User::factory()->suspended()->create()),
            'admin' => $this->actingAs(User::factory()->admin()->create()),
        };
    }
}
