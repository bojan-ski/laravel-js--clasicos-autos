<?php

namespace Tests\Feature\Seo;

use App\Enums\ListingStatus;
use App\Models\CarListing;
use App\Models\User;
use App\Support\Seo\Indexing;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Support\ApplicationRoutes;
use Tests\Support\RouteParameterMap;
use Tests\TestCase;

class SeoRoutesTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const array INDEXABLE_ROUTES = ['home', 'listings.index', 'listings.show', 'legal.privacy', 'legal.terms', 'legal.cookies'];

    public function test_no_static_robots_file_shadows_the_route(): void
    {
        $this->assertFileDoesNotExist(public_path('robots.txt'));
    }

    public function test_robots_disallows_everything_while_the_switch_is_off(): void
    {
        $response = $this->get(route('robots'));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
        $this->assertSame("User-agent: *\nDisallow: /\n", $response->getContent());
    }

    public function test_robots_lists_each_disallowed_path_and_the_sitemap_when_the_switch_is_on(): void
    {
        config(['seo.indexable' => true]);

        $response = $this->get(route('robots'));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
        $lines = explode("\n", rtrim((string) $response->getContent(), "\n"));
        $this->assertSame('User-agent: *', $lines[0]);
        foreach (Indexing::DISALLOWED_PATHS as $path) {
            $this->assertSame(1, count(array_keys($lines, "Disallow: {$path}", true)), "{$path} should be listed once.");
        }
        $this->assertSame(['', 'Sitemap: '.rtrim(config('app.url'), '/').'/sitemap.xml'], array_slice($lines, -2));
    }

    public function test_no_disallowed_path_blocks_an_indexable_page(): void
    {
        $routes = ApplicationRoutes::named();

        foreach (self::INDEXABLE_ROUTES as $name) {
            $path = (string) parse_url(route($name, RouteParameterMap::parametersFor($routes[$name])), PHP_URL_PATH);

            $this->assertNull($this->disallowingEntry($path === '' ? '/' : $path), "[{$name}] {$path} is blocked.");
        }

        $this->assertNull($this->disallowingEntry('/storage/listings/1/card.webp'));
        $this->assertNull($this->disallowingEntry('/assets/images/og-image.png'));
    }

    public function test_every_other_page_route_is_disallowed(): void
    {
        $exempt = [...self::INDEXABLE_ROUTES, 'robots', 'sitemap'];

        $undisallowed = [];

        foreach (ApplicationRoutes::named() as $name => $route) {
            if (! in_array('GET', $route->methods(), true) || in_array($name, $exempt, true) || $this->isListingSubPage($route)) {
                continue;
            }

            if ($this->disallowingEntry('/'.$route->uri()) === null) {
                $undisallowed[] = $name;
            }
        }

        $this->assertSame([], $undisallowed);
    }

    public function test_the_sitemap_lists_the_static_pages_then_the_public_listings(): void
    {
        $listings = CarListing::factory()->approved()->count(2)->create();

        $response = $this->get(route('sitemap'));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
        $this->assertStringStartsWith('<?xml version="1.0" encoding="UTF-8"?>', (string) $response->getContent());
        $this->assertSame([
            ['loc' => $this->appUrl('/'), 'lastmod' => null],
            ['loc' => $this->appUrl('/listings'), 'lastmod' => null],
            ['loc' => $this->appUrl('/privacy-policy'), 'lastmod' => null],
            ['loc' => $this->appUrl('/terms'), 'lastmod' => null],
            ['loc' => $this->appUrl('/cookie-policy'), 'lastmod' => null],
            ...$listings->sortBy('id')->map(fn (CarListing $listing): array => [
                'loc' => $this->appUrl("/listings/{$listing->id}"),
                'lastmod' => $listing->updated_at->toAtomString(),
            ])->values()->all(),
        ], $this->sitemapEntries($response));
    }

    public function test_the_sitemap_leaves_out_listings_that_are_not_public(): void
    {
        $public = CarListing::factory()->approved()->create();
        $hidden = [
            CarListing::factory()->create(),
            CarListing::factory()->rejected()->create(),
            CarListing::factory()->approved()->for(User::factory()->suspended(), 'owner')->create(),
        ];

        $locs = array_column($this->sitemapEntries($this->get(route('sitemap'))), 'loc');

        $this->assertContains($this->appUrl("/listings/{$public->id}"), $locs);
        foreach ($hidden as $listing) {
            $this->assertNotContains($this->appUrl("/listings/{$listing->id}"), $locs);
        }
    }

    public function test_a_listing_returned_to_pending_drops_out_of_the_next_sitemap(): void
    {
        $listing = CarListing::factory()->approved()->create();
        $loc = $this->appUrl("/listings/{$listing->id}");
        $this->assertContains($loc, array_column($this->sitemapEntries($this->get(route('sitemap'))), 'loc'));

        $listing->forceFill(['status' => ListingStatus::Pending, 'approved_at' => null])->save();

        $this->assertNotContains($loc, array_column($this->sitemapEntries($this->get(route('sitemap'))), 'loc'));
    }

    public function test_the_sitemap_query_count_does_not_grow_with_the_listings(): void
    {
        CarListing::factory()->approved()->count(2)->create();
        $few = $this->sitemapQueryCount();

        CarListing::factory()->approved()->count(18)->create();
        $many = $this->sitemapQueryCount();

        $this->assertSame($few, $many);
    }

    private function disallowingEntry(string $path): ?string
    {
        foreach (Indexing::DISALLOWED_PATHS as $entry) {
            if (Str::startsWith($path, $entry)) {
                return $entry;
            }
        }

        return null;
    }

    private function isListingSubPage(Route $route): bool
    {
        return Str::startsWith($route->uri(), 'listings/{listing}/');
    }

    /**
     * @return list<array{loc: string, lastmod: ?string}>
     */
    private function sitemapEntries(TestResponse $response): array
    {
        $xml = simplexml_load_string((string) $response->getContent());
        $this->assertNotFalse($xml);
        $this->assertSame('http://www.sitemaps.org/schemas/sitemap/0.9', $xml->getNamespaces()['']);

        $entries = [];

        foreach ($xml->url as $url) {
            $entries[] = ['loc' => (string) $url->loc, 'lastmod' => isset($url->lastmod) ? (string) $url->lastmod : null];
        }

        return $entries;
    }

    private function sitemapQueryCount(): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get(route('sitemap'))->assertOk();
        DB::disableQueryLog();

        return count(DB::getQueryLog());
    }

    private function appUrl(string $path): string
    {
        return rtrim(config('app.url'), '/').$path;
    }
}
