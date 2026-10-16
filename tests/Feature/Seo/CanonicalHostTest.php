<?php

namespace Tests\Feature\Seo;

use App\Models\CarListing;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CanonicalHostTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const string CANONICAL_HOST = 'https://clasicos.test';

    private const string REQUEST_HOST = 'http://www.example.test';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Storage::fake('public');
        config(['app.url' => self::CANONICAL_HOST, 'seo.indexable' => true]);
    }

    public function test_a_listing_page_on_another_host_declares_the_app_url(): void
    {
        $listing = CarListing::factory()->approved()->withImages(1)->create();

        $content = (string) $this->get(self::REQUEST_HOST."/listings/{$listing->id}")->assertOk()->getContent();

        $declared = [
            $this->attribute($content, '<link rel="canonical" href="'),
            $this->attribute($content, '<meta property="og:url" content="'),
            $this->attribute($content, '<meta property="og:image" content="'),
            $this->attribute($content, '<meta name="twitter:image" content="'),
            ...$this->structuredDataUrls($content),
        ];

        $this->assertSame(self::CANONICAL_HOST."/listings/{$listing->id}", $declared[0]);
        $this->assertGreaterThan(10, count($declared));
        foreach ($declared as $url) {
            $this->assertStringStartsWith(self::CANONICAL_HOST.'/', $url);
            $this->assertStringNotContainsString('www.example.test', $url);
        }
    }

    public function test_the_sitemap_and_robots_on_another_host_declare_the_app_url(): void
    {
        $listing = CarListing::factory()->approved()->create();

        $sitemap = (string) $this->get(self::REQUEST_HOST.'/sitemap.xml')->assertOk()->getContent();
        $robots = (string) $this->get(self::REQUEST_HOST.'/robots.txt')->assertOk()->getContent();

        preg_match_all('/<loc>([^<]*)<\/loc>/', $sitemap, $locs);
        $this->assertContains(self::CANONICAL_HOST.'/', $locs[1]);
        $this->assertContains(self::CANONICAL_HOST."/listings/{$listing->id}", $locs[1]);
        foreach ($locs[1] as $loc) {
            $this->assertStringStartsWith(self::CANONICAL_HOST.'/', $loc);
        }
        $this->assertStringNotContainsString('www.example.test', $sitemap);
        $this->assertStringEndsWith('Sitemap: '.self::CANONICAL_HOST."/sitemap.xml\n", $robots);
        $this->assertStringNotContainsString('www.example.test', $robots);
    }

    private function attribute(string $content, string $prefix): string
    {
        $this->assertSame(1, preg_match('/'.preg_quote($prefix, '/').'([^"]*)"/', $content, $match), "Missing {$prefix}");

        return html_entity_decode($match[1]);
    }

    /**
     * @return list<string>
     */
    private function structuredDataUrls(string $content): array
    {
        $this->assertSame(1, preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/s', $content, $match));

        $urls = [];
        $graph = json_decode($match[1], true, flags: JSON_THROW_ON_ERROR);

        array_walk_recursive(
            $graph,
            function (mixed $value, string|int $key) use (&$urls): void {
                if (is_string($value) && str_starts_with($value, 'http') && ! str_starts_with($value, 'https://schema.org')) {
                    $urls[] = $value;
                }
            },
        );

        return $urls;
    }
}
