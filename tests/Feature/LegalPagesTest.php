<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Legal\LegalSection;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LegalPagesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function documents(): array
    {
        return [
            'privacy' => ['legal.privacy', 'privacy'],
            'terms' => ['legal.terms', 'terms'],
            'cookies' => ['legal.cookies', 'cookies'],
        ];
    }

    #[DataProvider('documents')]
    public function test_guest_sees_the_whole_document(string $route, string $key): void
    {
        $sections = __("app.legal.{$key}.sections");

        $response = $this->get(route($route));

        $response->assertViewIs('pages.legal.show');
        $response->assertSeeInOrder([
            __("app.legal.{$key}.title"),
            ...__('app.legal.disclaimer'),
            __("app.legal.{$key}.intro"),
            ...array_column($sections, 'heading'),
        ]);
        foreach ($sections as $anchor => $section) {
            $response->assertSee('href="#'.$anchor.'"', false);
            $response->assertSee('<section id="'.$anchor.'"', false);
            foreach ($section['items'] ?? [$section['paragraph']] as $text) {
                $response->assertSee($text);
            }
        }
    }

    #[DataProvider('documents')]
    public function test_signed_in_user_sees_the_document(string $route, string $key): void
    {
        $response = $this->actingAs(User::factory()->make())->get(route($route));

        $response->assertViewIs('pages.legal.show');
        $response->assertSee(__("app.legal.{$key}.title"));
    }

    public function test_every_section_has_exactly_one_of_items_or_paragraph(): void
    {
        foreach (['privacy', 'terms', 'cookies'] as $key) {
            foreach (__("app.legal.{$key}.sections") as $anchor => $section) {
                $this->assertTrue(isset($section['items']) xor isset($section['paragraph']), "{$key}.{$anchor}");
            }
        }
    }

    public function test_a_section_without_items_or_paragraph_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new LegalSection(anchor: 'empty', heading: 'Empty');
    }

    public function test_page_title_is_the_heading_and_the_app_name(): void
    {
        $response = $this->get(route('legal.privacy'));

        $response->assertSee('<title>Privacy Policy page — Clásicos Autos</title>', false);
    }

    public function test_back_link_uses_a_same_host_previous_url(): void
    {
        $response = $this->from(route('legal.terms'))->get(route('legal.privacy'));

        $response->assertViewHas('backUrl', route('legal.terms'));
        $response->assertSee('href="'.route('legal.terms').'" class="inline-flex size-11', false);
    }

    public function test_back_link_falls_back_home_for_an_external_referer(): void
    {
        $response = $this->withHeader('Referer', 'https://evil.example/phish')->get(route('legal.privacy'));

        $response->assertViewHas('backUrl', route('home'));
    }

    public function test_back_link_falls_back_home_without_a_previous_url(): void
    {
        $response = $this->get(route('legal.privacy'));

        $response->assertViewHas('backUrl', route('home'));
    }

    public function test_back_link_falls_back_home_when_the_previous_url_is_the_same_page(): void
    {
        $response = $this->from(route('legal.privacy'))->get(route('legal.privacy'));

        $response->assertViewHas('backUrl', route('home'));
    }

    public function test_footer_and_menu_list_the_legal_links_in_order(): void
    {
        $response = $this->get(route('home'));

        $response->assertSeeInOrder([
            'href="'.route('legal.privacy').'"',
            'href="'.route('legal.terms').'"',
            'href="'.route('legal.cookies').'"',
        ], false);
        $response->assertSeeInOrder([
            __('app.legal.links.privacy'),
            __('app.legal.links.terms'),
            __('app.legal.links.cookies'),
        ]);
        $this->assertSame(2, substr_count($response->getContent(), 'href="'.route('legal.privacy').'"'));
    }

    #[DataProvider('documents')]
    public function test_current_legal_link_is_marked_in_footer_and_menu(string $route): void
    {
        $response = $this->get(route($route));

        $currentLink = '/href="'.preg_quote(route($route), '/').'"\s+aria-current="page"/';
        $this->assertSame(2, preg_match_all($currentLink, $response->getContent()));
        $this->assertSame(2, substr_count($response->getContent(), 'aria-current="page"'));
    }

    public function test_source_application_urls_are_not_routed(): void
    {
        $this->get('/privacy_policy')->assertNotFound();
        $this->get('/terms_and_conditions')->assertNotFound();
    }
}
