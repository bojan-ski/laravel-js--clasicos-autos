<?php

namespace Tests\Feature;

use App\Contracts\NavCounter;
use App\Enums\NavAudience;
use App\Enums\NavCountStyle;
use App\Models\User;
use App\Support\MainNavigation;
use App\Support\NavItem;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AppShellTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_audience_is_guest_without_a_user_and_member_with_one(): void
    {
        $this->assertSame(NavAudience::Guest, NavAudience::forUser(null));
        $this->assertSame(NavAudience::Member, NavAudience::forUser(User::factory()->make()));
    }

    public function test_links_are_filtered_by_audience(): void
    {
        $navigation = $this->app->make(MainNavigation::class);

        $this->assertSame(['home', 'listings'], array_column($navigation->linksFor(NavAudience::Guest, null), 'key'));
        $this->assertSame(['home', 'listings'], array_column($navigation->linksFor(NavAudience::Member, User::factory()->make()), 'key'));
        $this->assertSame([], $navigation->linksFor(NavAudience::Admin, null));
    }

    public function test_guest_sees_the_guest_header(): void
    {
        $this->get('/')
            ->assertSee('data-header-variant="guest"', false)
            ->assertSee(__('app.nav.home'));
    }

    public function test_signed_in_user_sees_the_member_header(): void
    {
        $this->actingAs(User::factory()->make())
            ->get('/')
            ->assertSee('data-header-variant="member"', false)
            ->assertSee(__('app.nav.home'));
    }

    public function test_admin_audience_renders_the_admin_header_without_home(): void
    {
        $this->blade('<x-global.header :audience="\App\Enums\NavAudience::Admin" />')
            ->assertSee('data-header-variant="admin"', false)
            ->assertSee(__('app.header.admin_tag'))
            ->assertDontSee(__('app.nav.home'))
            ->assertDontSee(__('app.nav.listings'));
    }

    public function test_the_wordmark_is_gold_light_on_the_dark_admin_bar_and_red_on_paper(): void
    {
        $dark = (string) $this->blade('<x-global.music-toggle :on-dark="true" />');
        $light = (string) $this->blade('<x-global.music-toggle />');

        $this->assertMatchesRegularExpression('/<button[^>]*data-music-toggle[^>]*class="[^"]*\btext-gold-light\b/', $dark);
        $this->assertDoesNotMatchRegularExpression('/<button[^>]*data-music-toggle[^>]*class="[^"]*\btext-red\b/', $dark);
        $this->assertStringContainsString('data-[state=on]:text-gold-light', $dark);
        $this->assertStringNotContainsString('data-[state=on]:text-red', $dark);

        $this->assertMatchesRegularExpression('/<button[^>]*data-music-toggle[^>]*class="[^"]*\btext-red\b/', $light);
        $this->assertStringContainsString('data-[state=on]:text-red', $light);
    }

    public function test_admin_layout_forces_the_admin_header(): void
    {
        $this->actingAs(User::factory()->make());

        $this->blade('<x-layouts.admin>Admin body</x-layouts.admin>')
            ->assertSee('data-header-variant="admin"', false)
            ->assertSee('Admin body');
    }

    public function test_the_skip_link_switches_to_the_gold_light_ring_under_the_dark_admin_header(): void
    {
        $this->actingAs(User::factory()->make());

        $this->blade('<x-layouts.admin>Admin body</x-layouts.admin>')
            ->assertSee('<a href="#main" class="sr-only z-80 bg-card px-4 py-2 text-sm text-red focus:not-sr-only focus:fixed focus:top-3 focus:left-3 [body:has([data-header-variant=admin])_&]:outline-gold-light">', false);
    }

    public function test_home_link_is_marked_current_only_on_the_home_route(): void
    {
        Route::middleware('web')->get('/_test/elsewhere', fn () => view('pages.home', ['latestListings' => collect()]));

        $this->get('/')->assertSee('aria-current="page"', false);
        $this->get('/_test/elsewhere')->assertDontSee('aria-current="page"', false);
    }

    public function test_listings_link_follows_home_and_is_current_on_the_listings_index(): void
    {
        $currentListingsLink = '/<a href="'.preg_quote(route('listings.index'), '/').'"\s+aria-current="page"/';

        $listingsPage = $this->get(route('listings.index'))
            ->assertSeeInOrder([__('app.nav.home'), __('app.nav.listings')]);

        $this->assertMatchesRegularExpression($currentListingsLink, $listingsPage->getContent());
        $this->assertDoesNotMatchRegularExpression($currentListingsLink, $this->get('/')->getContent());
    }

    public function test_count_badge_renders_only_for_a_non_zero_count(): void
    {
        $counter = new class implements NavCounter
        {
            public int $value = 0;

            public function count(?User $user): int
            {
                return $this->value;
            }
        };
        $this->app->instance($counter::class, $counter);
        $this->app->instance(MainNavigation::class, new class($this->app, $this->app['router'], $counter::class) extends MainNavigation
        {
            public function __construct($container, $router, private string $counterClass)
            {
                parent::__construct($container, $router);
            }

            public function items(): array
            {
                return [
                    new NavItem('inbox', 'app.nav.home', 'home', 'home', [NavAudience::Guest], $this->counterClass, NavCountStyle::Unread),
                ];
            }
        });

        $this->get('/')
            ->assertDontSee('data-nav-count', false)
            ->assertDontSee('data-menu-signal', false);

        $counter->value = 3;

        $this->get('/')
            ->assertSee('data-nav-count', false)
            ->assertSee('data-menu-signal', false)
            ->assertSee(__('app.header.new_count', ['count' => 3]));
    }

    public function test_success_flash_renders_a_status_pop_up(): void
    {
        $this->withSession(['success' => 'Saved it'])
            ->get('/')
            ->assertSee('data-flash', false)
            ->assertSee('role="status"', false)
            ->assertSee(__('app.flash.success_kicker'))
            ->assertSee('Saved it')
            ->assertDontSee('role="alert"', false)
            ->assertDontSee('data-flash-sticky', false);
    }

    public function test_error_flash_renders_an_alert_pop_up(): void
    {
        $this->withSession(['error' => 'Broke it'])
            ->get('/')
            ->assertSee('role="alert"', false)
            ->assertSee('data-flash-sticky', false)
            ->assertSee(__('app.flash.error_kicker'))
            ->assertSee('Broke it');
    }

    public function test_both_flashes_render_with_success_first(): void
    {
        $this->withSession(['success' => 'Saved it', 'error' => 'Broke it'])
            ->get('/')
            ->assertSeeInOrder(['Saved it', 'Broke it']);
    }

    public function test_no_flash_renders_without_session_messages(): void
    {
        $this->get('/')->assertDontSee('data-flash', false);
    }

    public function test_shell_renders_the_enhancement_hooks(): void
    {
        $this->get('/')
            ->assertSee(__('app.loading.caption'))
            ->assertSee('data-loading-overlay', false)
            ->assertSee('data-back-to-top', false)
            ->assertSee('data-mobile-menu', false)
            ->assertSee('data-music-toggle', false)
            ->assertSee('aria-label="'.e(__('app.music.label', ['name' => __('app.name')])).'"', false)
            ->assertSee('data-music-audio', false)
            ->assertSee('data-music-indicator', false);
    }

    public function test_page_references_no_third_party_font_host_and_no_autoplay(): void
    {
        $this->get('/')
            ->assertDontSee('fonts.googleapis.com')
            ->assertDontSee('fonts.gstatic.com')
            ->assertDontSee('fonts.bunny.net')
            ->assertDontSee('autoplay');
    }
}
