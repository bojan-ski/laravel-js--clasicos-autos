<?php

namespace Tests\Feature;

use Tests\TestCase;

class HomePageTest extends TestCase
{
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
}
