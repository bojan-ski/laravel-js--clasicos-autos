<?php

namespace Tests\Feature;

use App\Contracts\NavCounter;
use App\Enums\NavAudience;
use App\Enums\NavCountStyle;
use App\Models\CarListing;
use App\Models\User;
use App\Support\MainNavigation;
use App\Support\NavItem;
use App\Support\NewFeedbackCounter;
use App\Support\PendingListingsCounter;
use App\Support\UnreadMessagesCounter;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Route;
use InvalidArgumentException;
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

        $this->assertSame(['home', 'listings', 'compare'], array_column($navigation->linksFor(NavAudience::Guest, null), 'key'));
        $this->assertSame(['home', 'listings', 'compare', 'bookmarks', 'conversations', 'contact'], array_column($navigation->linksFor(NavAudience::Member, User::factory()->make()), 'key'));
        $this->assertSame(['users', 'admin-listings', 'admin-reports', 'admin-feedback', 'conversations'], array_column($navigation->linksFor(NavAudience::Admin, null), 'key'));
    }

    public function test_the_feedback_item_counts_new_messages_in_the_queue_style(): void
    {
        $items = array_column($this->app->make(MainNavigation::class)->items(), null, 'key');

        $this->assertSame(NewFeedbackCounter::class, $items['admin-feedback']->counter);
        $this->assertSame(NavCountStyle::Queue, $items['admin-feedback']->countStyle);
        $this->assertSame('app.header.count_labels.new', $items['admin-feedback']->countLabelKey);
        $this->assertSame([NavAudience::Admin], $items['admin-feedback']->audiences);
        $this->assertNull($items['contact']->counter);
        $this->assertSame([NavAudience::Member], $items['contact']->audiences);
    }

    public function test_the_member_header_tightens_between_lg_and_xl_and_never_wraps(): void
    {
        $this->blade('<x-global.header :audience="\App\Enums\NavAudience::Member" />')
            ->assertSee('class="mx-auto flex max-w-7xl items-center gap-8 px-5 py-4 md:px-14 md:py-5 lg:gap-5 lg:px-8 xl:gap-8 xl:px-14"', false)
            ->assertSee('class="mr-auto flex items-center gap-2.5 whitespace-nowrap"', false)
            ->assertSee('class="hidden items-center gap-5 text-sm whitespace-nowrap lg:flex xl:gap-7"', false)
            ->assertSee('class="hidden items-center gap-2.5 whitespace-nowrap lg:flex"', false)
            ->assertSee('data-desktop-query="(min-width: 64rem)" class="group lg:hidden"', false);
    }

    public function test_the_admin_header_switches_to_its_horizontal_nav_at_xl(): void
    {
        $this->blade('<x-global.header :audience="\App\Enums\NavAudience::Admin" />')
            ->assertSee('class="hidden items-center gap-5 text-sm whitespace-nowrap xl:flex xl:gap-7"', false)
            ->assertSee('class="hidden items-center gap-2.5 whitespace-nowrap xl:flex"', false)
            ->assertSee('data-desktop-query="(min-width: 80rem)" class="group xl:hidden"', false)
            ->assertDontSee('lg:flex', false)
            ->assertDontSee('lg:hidden', false);
    }

    public function test_the_admin_listings_item_counts_pending_listings_in_the_queue_style(): void
    {
        $items = array_column($this->app->make(MainNavigation::class)->items(), null, 'key');

        $this->assertSame(PendingListingsCounter::class, $items['admin-listings']->counter);
        $this->assertSame(NavCountStyle::Queue, $items['admin-listings']->countStyle);
        $this->assertSame('app.header.count_labels.pending', $items['admin-listings']->countLabelKey);
        $this->assertSame([NavAudience::Admin], $items['admin-listings']->audiences);
    }

    public function test_the_queue_badge_speaks_its_noun_in_the_header_and_the_mobile_menu(): void
    {
        CarListing::factory()->count(4)->create();

        $html = $this->actingAs(User::factory()->admin()->create())->get(route('admin.users'))->assertOk()->getContent();

        $this->assertSame(2, substr_count($html, '4<span class="sr-only"> pending</span>'));
    }

    public function test_a_count_label_without_a_counter_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Nav item [x] cannot set a count label without a counter.');

        new NavItem('x', 'app.nav.home', 'home', 'home', [NavAudience::Admin], null, null, 'app.header.count_labels.pending');
    }

    public function test_the_users_item_carries_no_counter(): void
    {
        $items = array_column($this->app->make(MainNavigation::class)->items(), null, 'key');

        $this->assertNull($items['users']->counter);
        $this->assertSame('admin.users', $items['users']->route);
    }

    public function test_the_conversations_item_counts_unread_messages(): void
    {
        $items = array_column($this->app->make(MainNavigation::class)->items(), null, 'key');

        $this->assertSame(UnreadMessagesCounter::class, $items['conversations']->counter);
        $this->assertSame(NavCountStyle::Unread, $items['conversations']->countStyle);
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
            ->assertSeeInOrder([__('app.nav.home'), __('app.nav.listings'), __('app.nav.compare'), __('app.nav.bookmarks'), __('app.nav.conversations')]);
    }

    public function test_admin_audience_renders_the_admin_header_without_home(): void
    {
        $this->blade('<x-global.header :audience="\App\Enums\NavAudience::Admin" />')
            ->assertSee('data-header-variant="admin"', false)
            ->assertSee(__('app.header.admin_tag'))
            ->assertDontSee(__('app.nav.home'))
            ->assertDontSee('href="'.route('listings.index').'"', false)
            ->assertSee('href="'.route('admin.listings').'"', false);
    }

    public function test_the_admin_header_renders_its_nav_with_the_conversations_link(): void
    {
        $this->blade('<x-global.header :audience="\App\Enums\NavAudience::Admin" />')
            ->assertSee('aria-label="'.__('app.a11y.main_navigation').'"', false)
            ->assertSee('href="'.route('conversations.index').'"', false)
            ->assertSee(__('app.nav.conversations'));
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
            ->assertSeeInOrder([__('app.nav.home'), __('app.nav.listings'), __('app.nav.compare')]);

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
