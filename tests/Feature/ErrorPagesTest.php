<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

class ErrorPagesTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_unknown_url_renders_the_custom_404(): void
    {
        $this->withoutVite()
            ->get('/definitely-not-a-page')
            ->assertNotFound()
            ->assertSee(__('app.errors.404.title'))
            ->assertSee(__('app.errors.404.stamp'));
    }

    public function test_the_404_offers_browse_listings_before_home(): void
    {
        $this->withoutVite()
            ->get('/definitely-not-a-page')
            ->assertNotFound()
            ->assertSeeInOrder([
                'href="'.route('listings.index').'"',
                __('app.errors.browse_listings'),
                'href="'.route('home').'"',
                __('app.errors.home'),
            ], false);
    }

    public function test_error_layout_renders_without_the_app_shell(): void
    {
        $this->withoutVite()
            ->get('/definitely-not-a-page')
            ->assertSee('href="'.route('home').'"', false)
            ->assertDontSee('data-header-variant', false)
            ->assertDontSee('data-music-toggle', false)
            ->assertDontSee('data-flash', false)
            ->assertDontSee('data-loading-overlay', false);
    }

    public function test_authorization_denial_renders_the_custom_403(): void
    {
        Route::middleware('web')->get('/_test/forbidden', fn () => abort(403));

        $this->withoutVite()
            ->get('/_test/forbidden')
            ->assertForbidden()
            ->assertSee(__('app.errors.403.title'))
            ->assertSee(__('app.errors.403.stamp'));
    }

    public function test_unhandled_exception_renders_the_self_contained_500(): void
    {
        config(['app.debug' => false]);
        Route::middleware('web')->get('/_test/boom', fn () => throw new RuntimeException('boom'));

        $this->get('/_test/boom')
            ->assertInternalServerError()
            ->assertSee(__('app.errors.500.title'))
            ->assertSee(__('app.errors.500.retry'))
            ->assertSee('href="'.url('/_test/boom').'"', false)
            ->assertDontSee('<link', false)
            ->assertDontSee('<script', false);
    }

    public function test_expired_session_redirects_home_with_an_error_flash(): void
    {
        Route::middleware('web')->get('/_test/expired', fn () => abort(419));

        $this->get('/_test/expired')
            ->assertRedirect(route('home'))
            ->assertSessionHas('error', __('app.errors.session_expired'));
    }

    public function test_expired_session_ignores_a_hostile_referer(): void
    {
        Route::middleware('web')->get('/_test/expired', fn () => abort(419));

        $this->withHeader('Referer', 'https://evil.example/phish')
            ->get('/_test/expired')
            ->assertRedirect(route('home'));
    }

    public function test_expired_session_redirect_shows_the_error_pop_up(): void
    {
        Route::middleware('web')->get('/_test/expired', fn () => abort(419));

        $this->withoutVite()
            ->followingRedirects()
            ->get('/_test/expired')
            ->assertOk()
            ->assertSee('role="alert"', false)
            ->assertSee(__('app.errors.session_expired'));
    }

    public function test_expired_session_keeps_419_for_json_requests(): void
    {
        Route::middleware('web')->get('/_test/expired', fn () => abort(419));

        $this->getJson('/_test/expired')
            ->assertStatus(419)
            ->assertJsonStructure(['message']);
    }
}
