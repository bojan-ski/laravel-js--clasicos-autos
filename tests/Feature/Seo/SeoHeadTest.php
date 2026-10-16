<?php

namespace Tests\Feature\Seo;

use App\Enums\BodyType;
use App\Enums\FuelType;
use App\Enums\Transmission;
use App\Models\CarListing;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SeoHeadTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const array SOCIAL_TAGS = [
        'property="og:site_name"',
        'property="og:type"',
        'property="og:locale"',
        'property="og:title"',
        'property="og:description"',
        'property="og:image"',
        'property="og:image:width"',
        'property="og:image:height"',
        'property="og:image:alt"',
        'name="twitter:card"',
        'name="twitter:title"',
        'name="twitter:description"',
        'name="twitter:image"',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Storage::fake('public');
        config(['seo.indexable' => true]);
    }

    /**
     * @return array<string, array{string, string, list<string>}>
     */
    public static function indexablePages(): array
    {
        return [
            'home' => ['home', 'app.seo.description', ['Organization', 'WebSite']],
            'listings index' => ['listings.index', 'app.seo.listings_description', ['Organization', 'WebSite', 'BreadcrumbList']],
            'listing' => ['listings.show', 'app.seo.listings_description', ['Organization', 'WebSite', 'BreadcrumbList', 'Car']],
            'privacy' => ['legal.privacy', 'app.seo.legal.privacy', ['Organization', 'WebSite', 'BreadcrumbList']],
            'terms' => ['legal.terms', 'app.seo.legal.terms', ['Organization', 'WebSite', 'BreadcrumbList']],
            'cookies' => ['legal.cookies', 'app.seo.legal.cookies', ['Organization', 'WebSite', 'BreadcrumbList']],
        ];
    }

    /**
     * @param  list<string>  $graphTypes
     */
    #[DataProvider('indexablePages')]
    public function test_an_indexable_page_has_the_full_head_once(string $route, string $descriptionKey, array $graphTypes): void
    {
        $parameters = $route === 'listings.show' ? [CarListing::factory()->approved()->create(['description' => ''])] : [];

        $head = $this->headOf($this->get(route($route, $parameters)));

        $this->assertSame(1, substr_count($head, '<title>'));
        $this->assertSame(1, substr_count($head, 'name="description"'));
        $this->assertStringContainsString('<meta name="description" content="'.e(__($descriptionKey)).'">', $head);
        $this->assertSame(1, substr_count($head, 'rel="canonical"'));
        $this->assertSame(1, substr_count($head, 'property="og:url"'));
        foreach (self::SOCIAL_TAGS as $tag) {
            $this->assertSame(1, substr_count($head, $tag), "{$tag} should appear once.");
        }
        $this->assertSame(1, substr_count($head, 'application/ld+json'));
        $this->assertSame($graphTypes, array_column($this->jsonLd($head)['@graph'], '@type'));
    }

    public function test_canonical_and_structured_data_render_with_the_switch_off(): void
    {
        config(['seo.indexable' => false]);

        $head = $this->headOf($this->get(route('home')));

        $this->assertStringContainsString('<link rel="canonical" href="'.$this->appUrl('/').'">', $head);
        $this->assertStringContainsString('application/ld+json', $head);
        $this->assertStringContainsString('<meta name="robots" content="noindex, nofollow">', $head);
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function nonIndexablePages(): array
    {
        return [
            'compare (guest)' => ['compare.show', false],
            'profile (app user)' => ['profile', true],
        ];
    }

    #[DataProvider('nonIndexablePages')]
    public function test_a_non_indexable_page_has_the_social_tags_but_no_canonical_or_structured_data(string $route, bool $signedIn): void
    {
        if ($signedIn) {
            $this->actingAs(User::factory()->create());
        }

        $head = $this->headOf($this->get(route($route)));

        $this->assertStringContainsString('<meta name="description" content="'.e(__('app.seo.description')).'">', $head);
        foreach (self::SOCIAL_TAGS as $tag) {
            $this->assertSame(1, substr_count($head, $tag), "{$tag} should appear once.");
        }
        $this->assertStringNotContainsString('rel="canonical"', $head);
        $this->assertStringNotContainsString('og:url', $head);
        $this->assertStringNotContainsString('application/ld+json', $head);
    }

    public function test_og_and_twitter_titles_repeat_the_document_title(): void
    {
        $listing = CarListing::factory()->approved()->create(['name' => '1957 Bel Air']);

        $head = $this->headOf($this->get(route('listings.show', $listing)));

        $this->assertStringContainsString('<title>1957 Bel Air — Clásicos Autos</title>', $head);
        $this->assertStringContainsString('<meta property="og:title" content="1957 Bel Air — Clásicos Autos">', $head);
        $this->assertStringContainsString('<meta name="twitter:title" content="1957 Bel Air — Clásicos Autos">', $head);
    }

    public function test_a_long_listing_description_becomes_an_excerpt(): void
    {
        $listing = CarListing::factory()->approved()->create(['description' => str_repeat('Original paint and chrome. ', 20)]);

        $head = $this->headOf($this->get(route('listings.show', $listing)));

        $this->assertSame(1, preg_match('/<meta name="description" content="([^"]*)">/', $head, $match));
        $this->assertLessThanOrEqual(160, mb_strlen($match[1]));
        $this->assertStringEndsWith('…', $match[1]);
        $this->assertSame($match[1], $this->jsonLdNode($head, 'Car')['description']);
    }

    public function test_no_page_renders_an_empty_description(): void
    {
        $listing = CarListing::factory()->approved()->create(['description' => '   ']);

        foreach ([route('home'), route('listings.show', $listing), route('compare.show'), route('login')] as $url) {
            $this->assertStringNotContainsString('name="description" content=""', $this->headOf($this->get($url)));
        }
    }

    public function test_the_car_node_describes_the_listing(): void
    {
        $listing = CarListing::factory()->approved()->withImages(2)->create([
            'year' => 1957,
            'mileage' => 84000,
            'price' => 45000,
            'exterior_color' => 'Turquoise',
            'transmission' => Transmission::Manual,
            'fuel_type' => FuelType::Gasoline,
            'body_type' => BodyType::Coupe,
        ]);
        $listing->load('maker', 'images');

        $car = $this->jsonLdNode($this->headOf($this->get(route('listings.show', $listing))), 'Car');

        $this->assertSame($this->appUrl("/listings/{$listing->id}").'#car', $car['@id']);
        $this->assertSame($listing->name, $car['name']);
        $this->assertSame(['@type' => 'Brand', 'name' => $listing->maker->name], $car['brand']);
        $this->assertSame($listing->model, $car['model']);
        $this->assertSame('1957', $car['vehicleModelDate']);
        $this->assertSame(['@type' => 'QuantitativeValue', 'value' => 84000, 'unitCode' => 'KMT'], $car['mileageFromOdometer']);
        $this->assertSame('Turquoise', $car['color']);
        $this->assertSame(Transmission::Manual->label(), $car['vehicleTransmission']);
        $this->assertSame(FuelType::Gasoline->label(), $car['fuelType']);
        $this->assertSame(BodyType::Coupe->label(), $car['bodyType']);
        $this->assertSame($this->appUrl(parse_url($listing->images->first()->url(), PHP_URL_PATH)), $car['image']);
        $this->assertSame([
            '@type' => 'Offer',
            'price' => 45000,
            'priceCurrency' => 'USD',
            'availability' => 'https://schema.org/InStock',
            'url' => $this->appUrl("/listings/{$listing->id}"),
        ], $car['offers']);
    }

    public function test_the_head_never_carries_the_sellers_contact_details(): void
    {
        $owner = User::factory()->create(['email' => 'seller@example.test', 'phone_number' => '+385911234567']);
        $listing = CarListing::factory()->approved()->for($owner, 'owner')->create();

        $head = $this->headOf($this->get(route('listings.show', $listing)));

        $this->assertStringNotContainsString('seller@example.test', $head);
        $this->assertStringNotContainsString('385911234567', $head);
        $this->assertStringNotContainsString($owner->username, $head);
    }

    public function test_the_breadcrumbs_run_from_home_to_the_listing(): void
    {
        $listing = CarListing::factory()->approved()->create();

        $breadcrumbs = $this->jsonLdNode($this->headOf($this->get(route('listings.show', $listing))), 'BreadcrumbList');

        $this->assertSame([
            ['@type' => 'ListItem', 'position' => 1, 'name' => __('app.nav.home'), 'item' => $this->appUrl('/')],
            ['@type' => 'ListItem', 'position' => 2, 'name' => __('app.nav.listings'), 'item' => $this->appUrl('/listings')],
            ['@type' => 'ListItem', 'position' => 3, 'name' => $listing->name, 'item' => $this->appUrl("/listings/{$listing->id}")],
        ], $breadcrumbs['itemListElement']);
    }

    public function test_a_listing_with_images_shares_its_card_image(): void
    {
        $listing = CarListing::factory()->approved()->withImages(2)->create();
        $first = $listing->images()->orderBy('position')->first();

        $head = $this->headOf($this->get(route('listings.show', $listing)));

        $this->assertStringContainsString('<meta property="og:image" content="'.$this->appUrl(parse_url($first->cardUrl(), PHP_URL_PATH)).'">', $head);
        $this->assertStringContainsString('<meta property="og:image:width" content="800">', $head);
        $this->assertStringContainsString('<meta property="og:image:height" content="600">', $head);
        $this->assertStringContainsString('<meta property="og:image:alt" content="'.e($listing->name).'">', $head);
    }

    public function test_a_listing_without_images_and_other_pages_share_the_site_image(): void
    {
        $listing = CarListing::factory()->approved()->create();

        foreach ([route('listings.show', $listing), route('home'), route('legal.terms')] as $url) {
            $head = $this->headOf($this->get($url));

            $this->assertStringContainsString('<meta property="og:image" content="'.$this->appUrl('/assets/images/og-image.png').'">', $head);
            $this->assertStringContainsString('<meta property="og:image:width" content="1200">', $head);
            $this->assertStringContainsString('<meta property="og:image:height" content="630">', $head);
            $this->assertStringContainsString('<meta property="og:image:alt" content="'.e(__('app.seo.share_image_alt')).'">', $head);
            $this->assertStringContainsString('<meta name="twitter:image" content="'.$this->appUrl('/assets/images/og-image.png').'">', $head);
        }
    }

    public function test_home_preloads_the_hero_photo_it_renders(): void
    {
        $response = $this->get(route('home'));
        $hero = asset('assets/images/hero.jpg');

        $this->assertSame(1, substr_count($this->headOf($response), 'rel="preload" as="image"'));
        $this->assertStringContainsString('<link rel="preload" as="image" href="'.$hero.'" fetchpriority="high">', $this->headOf($response));
        $response->assertSee('src="'.$hero.'"', false);
    }

    public function test_a_listing_preloads_the_main_gallery_photo_it_renders(): void
    {
        $listing = CarListing::factory()->approved()->withImages(3)->create();
        $mainPhoto = $listing->images()->orderBy('position')->first()->url();

        $response = $this->get(route('listings.show', $listing));

        $this->assertSame(1, substr_count($this->headOf($response), 'rel="preload" as="image"'));
        $this->assertStringContainsString('<link rel="preload" as="image" href="'.$mainPhoto.'" fetchpriority="high">', $this->headOf($response));
        $response->assertSee('data-gallery-main data-develop src="'.$mainPhoto.'"', false);
    }

    public function test_a_page_without_a_hero_photo_has_no_preload(): void
    {
        $this->assertStringNotContainsString('rel="preload" as="image"', $this->headOf($this->get(route('legal.privacy'))));
    }

    public function test_record_text_cannot_close_the_structured_data_script(): void
    {
        $listing = CarListing::factory()->approved()->create(['name' => 'Bel Air </script><script>alert(1)</script>']);

        $head = $this->headOf($this->get(route('listings.show', $listing)));

        $this->assertStringNotContainsString('<script>alert(1)', $head);
        $this->assertSame(1, substr_count($head, '</script>'));
        $this->assertSame($listing->name, $this->jsonLdNode($head, 'Car')['name']);
    }

    public function test_every_app_page_links_the_icons_the_manifest_and_the_theme_colour(): void
    {
        $head = $this->headOf($this->get(route('home')));

        foreach (['favicon-32.png', 'favicon-16.png', 'apple-touch-icon.png', 'site.webmanifest'] as $file) {
            $this->assertStringContainsString('href="'.asset($file).'"', $head);
            $this->assertFileExists(public_path($file));
        }
        $this->assertStringContainsString('<meta name="theme-color" content="#c22d24">', $head);
        $this->assertFileExists(public_path('assets/images/og-image.png'));
        $this->assertFileExists(public_path(config('seo.logo')));
    }

    public function test_only_the_head_components_and_the_error_documents_emit_meta_tags(): void
    {
        $allowed = [
            resource_path('views/components/global/head.blade.php'),
            resource_path('views/components/global/robots-meta.blade.php'),
            resource_path('views/components/layouts/error.blade.php'),
            resource_path('views/errors/500.blade.php'),
        ];

        $emitters = [];

        foreach (File::allFiles(resource_path('views')) as $file) {
            if (str_contains($file->getContents(), '<meta') && ! in_array(realpath($file->getPathname()), array_map('realpath', $allowed), true)) {
                $emitters[] = $file->getRelativePathname();
            }
        }

        $this->assertSame([], $emitters);
    }

    private function headOf(TestResponse $response): string
    {
        $response->assertOk();

        $this->assertSame(1, preg_match('/<head>(.*)<\/head>/s', (string) $response->getContent(), $match));

        return $match[1];
    }

    /**
     * @return array<string, mixed>
     */
    private function jsonLd(string $head): array
    {
        $this->assertSame(1, preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/s', $head, $match));

        return json_decode($match[1], true, flags: JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<string, mixed>
     */
    private function jsonLdNode(string $head, string $type): array
    {
        $nodes = array_values(array_filter($this->jsonLd($head)['@graph'], fn (array $node): bool => $node['@type'] === $type));

        $this->assertCount(1, $nodes);

        return $nodes[0];
    }

    private function appUrl(string $path): string
    {
        return rtrim(config('app.url'), '/').$path;
    }
}
