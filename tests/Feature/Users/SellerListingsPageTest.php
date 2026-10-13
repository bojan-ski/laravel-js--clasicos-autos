<?php

namespace Tests\Feature\Users;

use App\Models\CarListing;
use App\Models\User;
use App\Services\ListingBrowseService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SellerListingsPageTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_a_guest_is_redirected_to_login(): void
    {
        $seller = User::factory()->create();

        $this->get(route('users.listings', $seller))->assertRedirect(route('login'));
    }

    public function test_a_non_numeric_or_unknown_id_is_not_found(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/users/abc/listings')->assertNotFound();
        $this->get('/users/999999/listings')->assertNotFound();
    }

    /**
     * @return array<string, array{string, string, int}>
     */
    public static function accessMatrix(): array
    {
        return [
            'active seller × seller' => ['active', 'seller', 200],
            'active seller × admin' => ['active', 'admin', 200],
            'active seller × stranger' => ['active', 'stranger', 200],
            'active seller × suspended stranger' => ['active', 'suspended', 200],
            'suspended seller × seller' => ['suspended', 'seller', 200],
            'suspended seller × admin' => ['suspended', 'admin', 200],
            'suspended seller × stranger' => ['suspended', 'stranger', 404],
            'admin id × that admin' => ['admin', 'seller', 404],
            'admin id × another admin' => ['admin', 'admin', 404],
            'admin id × app user' => ['admin', 'stranger', 404],
        ];
    }

    #[DataProvider('accessMatrix')]
    public function test_access_follows_the_seller_state_and_the_viewer(string $sellerState, string $viewerKind, int $status): void
    {
        $seller = match ($sellerState) {
            'active' => User::factory()->create(),
            'suspended' => User::factory()->suspended()->create(),
            'admin' => User::factory()->admin()->create(),
        };

        $this->actingAs($this->viewer($viewerKind, $seller))
            ->get(route('users.listings', $seller))
            ->assertStatus($status);
    }

    public function test_a_stranger_sees_only_the_sellers_public_listings_without_status(): void
    {
        $seller = User::factory()->create();
        $approved = CarListing::factory()->approved()->for($seller, 'owner')->create(['name' => 'Visible Bel Air']);
        $pending = CarListing::factory()->for($seller, 'owner')->create(['name' => 'Waiting Nomad']);
        $rejected = CarListing::factory()->rejected()->for($seller, 'owner')->create(['name' => 'Refused Skylark']);
        $otherSellers = CarListing::factory()->approved()->create(['name' => 'Someone Elses Impala']);

        $response = $this->page($seller, User::factory()->create());

        $response->assertSee($approved->name)
            ->assertDontSee($pending->name)
            ->assertDontSee($rejected->name)
            ->assertDontSee($otherSellers->name)
            ->assertViewHas('showsStatus', false);
        $this->assertSame(1, $response->viewData('listings')->total());
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function everyStatusViewers(): array
    {
        return [
            'active seller, own view' => ['active', 'seller'],
            'active seller, admin view' => ['active', 'admin'],
            'suspended seller, own view' => ['suspended', 'seller'],
            'suspended seller, admin view' => ['suspended', 'admin'],
        ];
    }

    #[DataProvider('everyStatusViewers')]
    public function test_the_seller_and_admins_see_every_status_with_stamps_and_the_reason(string $sellerState, string $viewerKind): void
    {
        $seller = $sellerState === 'suspended' ? User::factory()->suspended()->create() : User::factory()->create();
        $approved = CarListing::factory()->approved()->for($seller, 'owner')->create(['name' => 'Visible Bel Air']);
        $pending = CarListing::factory()->for($seller, 'owner')->create(['name' => 'Waiting Nomad']);
        $rejected = CarListing::factory()->rejected('Rusted sills')->for($seller, 'owner')->create(['name' => 'Refused Skylark']);
        CarListing::factory()->approved()->create(['name' => 'Someone Elses Impala']);

        $response = $this->page($seller, $this->viewer($viewerKind, $seller));

        $response->assertSee($approved->name)
            ->assertSee($pending->name)
            ->assertSee($rejected->name)
            ->assertSee('Rusted sills')
            ->assertDontSee('Someone Elses Impala')
            ->assertViewHas('showsStatus', true);
        $html = (string) $response->getContent();
        $this->assertSame(1, substr_count($html, 'text-status-pending'));
        $this->assertSame(1, substr_count($html, 'text-status-rejected'));
        $this->assertSame(0, substr_count($html, 'text-status-approved'));
    }

    public function test_the_public_only_query_also_hides_a_suspended_sellers_approved_listings(): void
    {
        $seller = User::factory()->suspended()->create();
        CarListing::factory()->approved()->for($seller, 'owner')->create();

        $this->assertSame(0, app(ListingBrowseService::class)->sellerListings($seller, false)->total());
        $this->assertSame(1, app(ListingBrowseService::class)->sellerListings($seller, true)->total());
    }

    public function test_listings_are_newest_first_with_the_id_breaking_ties(): void
    {
        $seller = User::factory()->create();
        $oldest = CarListing::factory()->approved()->for($seller, 'owner')->create(['name' => 'Oldest Packard', 'created_at' => now()->subDays(3)]);
        $tiedFirst = CarListing::factory()->approved()->for($seller, 'owner')->create(['name' => 'Tied Hudson', 'created_at' => now()->subDay()]);
        $tiedSecond = CarListing::factory()->approved()->for($seller, 'owner')->create(['name' => 'Tied Studebaker', 'created_at' => $tiedFirst->created_at]);
        $newest = CarListing::factory()->approved()->for($seller, 'owner')->create(['name' => 'Newest Rambler', 'created_at' => now()->subHour()]);

        $this->page($seller, User::factory()->create())
            ->assertSeeInOrder([$newest->name, $tiedSecond->name, $tiedFirst->name, $oldest->name]);
    }

    public function test_twelve_listings_fill_a_page_and_the_thirteenth_moves_to_page_two(): void
    {
        $seller = User::factory()->create();
        $oldest = CarListing::factory()->approved()->for($seller, 'owner')->create(['name' => 'Oldest Edsel', 'created_at' => now()->subDays(2)]);
        CarListing::factory()->approved()->for($seller, 'owner')->count(12)->create(['created_at' => now()->subHour()]);
        $this->actingAs(User::factory()->create());

        $first = $this->get(route('users.listings', $seller));
        $second = $this->get(route('users.listings', ['user' => $seller, 'page' => 2]));

        $first->assertSee(__('app.pagination.label'))->assertDontSee($oldest->name);
        $this->assertSame(12, $first->viewData('listings')->count());
        $second->assertSee($oldest->name);
        $this->assertSame(1, $second->viewData('listings')->count());
    }

    public function test_the_joined_line_counts_what_each_viewer_sees(): void
    {
        $seller = User::factory()->create(['created_at' => Carbon::parse('2026-03-14 10:00:00')]);
        CarListing::factory()->approved()->for($seller, 'owner')->create();
        CarListing::factory()->for($seller, 'owner')->create();
        CarListing::factory()->rejected()->for($seller, 'owner')->create();

        $this->page($seller, User::factory()->create())
            ->assertViewHas('joined', '14 March 2026')
            ->assertSee('Joined 14 March 2026 · 1 listing<', false);
        $this->page($seller, $seller)->assertSee('Joined 14 March 2026 · 3 listings<', false);
        $this->page($seller, User::factory()->admin()->create())->assertSee('Joined 14 March 2026 · 3 listings<', false);
    }

    /**
     * @return array<string, array{string, int}>
     */
    public static function sendMessageViewers(): array
    {
        return [
            'active stranger sees it' => ['stranger', 1],
            'the seller does not' => ['seller', 0],
            'an admin does not' => ['admin', 0],
            'a suspended app user does not' => ['suspended', 0],
        ];
    }

    #[DataProvider('sendMessageViewers')]
    public function test_send_message_is_a_disabled_outlined_button_for_active_non_seller_app_users(string $viewerKind, int $expected): void
    {
        $seller = User::factory()->create();

        $response = $this->page($seller, $this->viewer($viewerKind, $seller));

        $response->assertViewHas('showsSendMessage', $expected === 1);
        $this->assertSame($expected, preg_match_all('/<button[^>]*\sdisabled(="disabled")?>'.preg_quote(__('app.sellers.send_message'), '/').'<\/button>/', (string) $response->getContent()));
    }

    public function test_the_send_message_button_shows_even_when_the_stranger_sees_no_listings(): void
    {
        $seller = User::factory()->create();
        CarListing::factory()->for($seller, 'owner')->create();

        $this->page($seller, User::factory()->create())
            ->assertViewHas('showsSendMessage', true)
            ->assertSee(__('app.sellers.empty'));
    }

    public function test_with_no_listings_the_header_and_empty_message_render_without_a_grid(): void
    {
        $seller = User::factory()->create(['username' => 'jdoe']);

        $response = $this->page($seller, User::factory()->create());

        $response->assertSee(__('app.sellers.title', ['username' => 'jdoe']))
            ->assertSee('· 0 listings')
            ->assertSee(__('app.sellers.empty'))
            ->assertDontSee('md:grid-cols-3', false)
            ->assertDontSee(__('app.pagination.label'));
    }

    public function test_a_page_beyond_the_last_shows_the_header_and_pagination_only(): void
    {
        $seller = User::factory()->create(['username' => 'jdoe']);
        $listing = CarListing::factory()->approved()->for($seller, 'owner')->create(['name' => 'Only Listed Corvair']);

        $response = $this->actingAs(User::factory()->create())->get(route('users.listings', ['user' => $seller, 'page' => 5]));

        $response->assertOk()
            ->assertSee(__('app.sellers.title', ['username' => 'jdoe']))
            ->assertSee(__('app.pagination.label'))
            ->assertDontSee($listing->name)
            ->assertDontSee(__('app.sellers.empty'));
    }

    public function test_the_back_link_is_the_same_host_referer(): void
    {
        $seller = User::factory()->create();
        $listing = CarListing::factory()->approved()->for($seller, 'owner')->create();

        $this->actingAs(User::factory()->create())
            ->call('GET', route('users.listings', $seller), server: $this->transformHeadersToServerVars(['Referer' => route('listings.show', $listing)]))
            ->assertViewHas('backUrl', route('listings.show', $listing));
    }

    public function test_the_back_link_falls_back_to_the_listings_index(): void
    {
        $seller = User::factory()->create();

        $this->actingAs(User::factory()->create())
            ->call('GET', route('users.listings', $seller), server: $this->transformHeadersToServerVars(['Referer' => 'https://evil.example.com/listings']))
            ->assertViewHas('backUrl', route('listings.index'));
    }

    public function test_the_page_has_its_title_spanish_kicker_hidden_stamp_and_three_column_grid(): void
    {
        $seller = User::factory()->create(['username' => 'ana-brito']);
        CarListing::factory()->approved()->for($seller, 'owner')->create();

        $response = $this->page($seller, User::factory()->create());
        $html = (string) $response->getContent();

        $response->assertSee('<title>'.e(__('app.sellers.title', ['username' => 'ana-brito'])).' — Clásicos Autos</title>', false)
            ->assertSee('grid gap-9 md:grid-cols-3', false);
        $this->assertMatchesRegularExpression('/<span[^>]*lang="es"[^>]*>'.preg_quote(__('app.sellers.kicker'), '/').'<\/span>/', $html);
        $this->assertMatchesRegularExpression('/<span[^>]*aria-hidden="true"[^>]*>\s*<span[^>]*>\s*<span[^>]*>AB<\/span>/', $html);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function queryCountViewers(): array
    {
        return [
            'stranger' => ['stranger'],
            'seller' => ['seller'],
            'admin' => ['admin'],
        ];
    }

    #[DataProvider('queryCountViewers')]
    public function test_the_query_count_does_not_grow_with_the_sellers_listings(string $viewerKind): void
    {
        $seller = User::factory()->create();
        $this->actingAs($this->viewer($viewerKind, $seller));
        CarListing::factory()->approved()->withImages()->for($seller, 'owner')->create();
        CarListing::factory()->rejected()->withImages()->for($seller, 'owner')->create();
        $two = $this->queriesFor(route('users.listings', $seller));

        CarListing::factory()->approved()->withImages()->for($seller, 'owner')->count(5)->create();
        CarListing::factory()->rejected()->withImages()->for($seller, 'owner')->count(5)->create();
        $twelve = $this->queriesFor(route('users.listings', $seller));

        $this->assertSame($two, $twelve);
    }

    private function page(User $seller, User $viewer): TestResponse
    {
        return $this->actingAs($viewer)
            ->get(route('users.listings', $seller))
            ->assertOk()
            ->assertViewIs('pages.users.listings');
    }

    private function viewer(string $kind, User $seller): User
    {
        return match ($kind) {
            'seller' => $seller,
            'admin' => User::factory()->admin()->create(),
            'stranger' => User::factory()->create(),
            'suspended' => User::factory()->suspended()->create(),
        };
    }

    private function queriesFor(string $url): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get($url)->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }
}
