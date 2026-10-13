<?php

namespace Tests\Feature;

use App\Models\CarListing;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class HomePageTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_home_route_resolves_to_the_site_root(): void
    {
        $this->assertSame('/', route('home', absolute: false));
    }

    public function test_home_page_renders_the_home_view(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertViewIs('pages.home');
    }

    public function test_home_page_uses_the_default_title(): void
    {
        $this->get('/')
            ->assertSee('<title>'.e(__('app.meta.default_title')).'</title>', false);
    }

    public function test_home_page_shows_the_hero_postcard(): void
    {
        $this->get('/')
            ->assertSee(__('app.home.hero.title'))
            ->assertSee(__('app.home.hero.line'))
            ->assertSee(__('app.home.hero.kicker'))
            ->assertSee(__('app.home.hero.overlay'))
            ->assertSee(__('app.home.hero.stamp_country'))
            ->assertSee(__('app.home.hero.stamp_year'))
            ->assertSee(__('app.home.hero.stamp_post'))
            ->assertSee(__('app.home.hero.image_alt'));
    }

    public function test_home_page_shows_the_about_section(): void
    {
        $this->get('/')
            ->assertSee(__('app.home.about.title'))
            ->assertSee(__('app.home.about.body'));
    }

    public function test_the_hero_links_see_offers_to_the_listings_index(): void
    {
        $this->assertMatchesRegularExpression(
            '/<a href="'.preg_quote(route('listings.index'), '/').'"[^>]*>'.__('app.home.hero.cta').'<\/a>/',
            $this->get('/')->getContent(),
        );
    }

    public function test_home_shows_the_three_newest_public_listings_and_the_all_listings_link(): void
    {
        foreach (range(1, 4) as $age) {
            CarListing::factory()->approved()->create(['name' => "Home Car {$age}", 'created_at' => now()->subMinutes($age)]);
        }

        $response = $this->get('/')
            ->assertSee(__('app.home.latest.title'))
            ->assertSeeInOrder(['Home Car 1', 'Home Car 2', 'Home Car 3'])
            ->assertDontSee('Home Car 4');

        $this->assertMatchesRegularExpression(
            '/<a href="'.preg_quote(route('listings.index'), '/').'"[^>]*>'.__('app.home.latest.all').' /',
            $response->getContent(),
        );
    }

    public function test_home_excludes_listings_that_are_not_public(): void
    {
        CarListing::factory()->approved()->create(['name' => 'Public Car']);
        CarListing::factory()->create(['name' => 'Pending Car']);
        CarListing::factory()->rejected()->create(['name' => 'Rejected Car']);
        CarListing::factory()->for(User::factory()->suspended(), 'owner')->approved()->create(['name' => 'Suspended Owner Car']);

        $this->get('/')
            ->assertSee('Public Car')
            ->assertDontSee('Pending Car')
            ->assertDontSee('Rejected Car')
            ->assertDontSee('Suspended Owner Car');
    }

    public function test_home_omits_the_latest_section_without_a_public_listing(): void
    {
        CarListing::factory()->create();

        $this->get('/')
            ->assertOk()
            ->assertDontSee(__('app.home.latest.title'))
            ->assertDontSee(__('app.listings.index.empty'));
    }

    public function test_home_renders_the_latest_cards_without_lazy_loading(): void
    {
        CarListing::factory()->count(3)->approved()->withImages()->create();

        $this->get('/')->assertOk();
    }
}
