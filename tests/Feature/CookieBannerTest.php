<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

class CookieBannerTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const string BANNER = '<section data-cookie-banner';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_banner_shows_without_the_consent_cookie(): void
    {
        $response = $this->get(route('home'));

        $response->assertSee(self::BANNER, false);
        $response->assertSee(__('app.cookie_banner.notice'));
        $response->assertSee('href="'.route('legal.cookies').'"', false);
        $response->assertSee('action="'.route('cookie-consent.store').'" data-loading-skip', false);
        $response->assertSee('name="csrf-token"', false);
    }

    public function test_the_page_reserves_bottom_scroll_padding_while_the_banner_is_present(): void
    {
        $this->get(route('home'))
            ->assertSee('<html lang="en" class="has-data-cookie-banner:scroll-pb-60 md:has-data-cookie-banner:scroll-pb-28">', false);
    }

    public function test_banner_is_hidden_once_consent_is_accepted(): void
    {
        $response = $this->withCookie('cookie_consent', 'accepted')->get(route('home'));

        $response->assertDontSee(self::BANNER, false);
        $response->assertDontSee(__('app.cookie_banner.notice'));
    }

    public function test_banner_shows_for_any_other_cookie_value(): void
    {
        $response = $this->withCookie('cookie_consent', 'declined')->get(route('home'));

        $response->assertSee(self::BANNER, false);
    }

    public function test_not_found_page_has_no_banner_and_no_csrf_meta(): void
    {
        $response = $this->get('/definitely-not-a-page');

        $response->assertNotFound();
        $response->assertDontSee(self::BANNER, false);
        $response->assertDontSee('name="csrf-token"', false);
    }

    public function test_server_error_page_has_no_banner(): void
    {
        config(['app.debug' => false]);
        Route::middleware('web')->get('/_test/boom', fn () => throw new RuntimeException('boom'));

        $response = $this->get('/_test/boom');

        $response->assertInternalServerError();
        $response->assertDontSee(__('app.cookie_banner.notice'));
    }

    public function test_banner_renders_once_in_the_guest_and_admin_layouts(): void
    {
        $this->actingAs(User::factory()->make());

        $guest = $this->blade('<x-layouts.guest>Body</x-layouts.guest>');
        $admin = $this->blade('<x-layouts.admin>Body</x-layouts.admin>');

        $this->assertSame(1, substr_count((string) $guest, self::BANNER));
        $this->assertSame(1, substr_count((string) $admin, self::BANNER));
    }

    public function test_accepting_redirects_back_with_the_cookie_and_no_flash(): void
    {
        $response = $this->from(route('legal.terms'))->post(route('cookie-consent.store'));

        $response->assertRedirect(route('legal.terms'));
        $response->assertCookie('cookie_consent', 'accepted');
        $response->assertSessionMissing('success');
        $response->assertSessionMissing('error');
    }

    public function test_accepting_without_a_previous_url_redirects_home(): void
    {
        $response = $this->post(route('cookie-consent.store'));

        $response->assertRedirect(route('home'));
        $response->assertCookie('cookie_consent', 'accepted');
    }

    public function test_accepting_again_returns_the_same_response(): void
    {
        $response = $this->withCookie('cookie_consent', 'accepted')
            ->from(route('legal.terms'))
            ->post(route('cookie-consent.store'));

        $response->assertRedirect(route('legal.terms'));
        $response->assertCookie('cookie_consent', 'accepted');
    }

    public function test_accepting_over_json_returns_no_content_with_the_cookie(): void
    {
        $response = $this->postJson(route('cookie-consent.store'), ['ignored' => 'input']);

        $response->assertNoContent();
        $response->assertCookie('cookie_consent', 'accepted');
    }

    public function test_consent_cookie_is_encrypted_http_only_and_lasts_twelve_months(): void
    {
        $this->travelTo('2026-01-01 00:00:00');

        $cookie = $this->post(route('cookie-consent.store'))->getCookie('cookie_consent', false);

        $this->assertSame(strtotime('2027-01-01 00:00:00 UTC'), $cookie->getExpiresTime());
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertSame('/', $cookie->getPath());
        $this->assertNotSame('accepted', $cookie->getValue());
    }
}
