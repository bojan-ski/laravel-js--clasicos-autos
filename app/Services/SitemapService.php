<?php

namespace App\Services;

use App\Models\CarListing;
use App\Support\Seo\CanonicalUrl;
use App\Support\Seo\Indexing;
use Illuminate\Routing\Router;
use Illuminate\Support\LazyCollection;

class SitemapService
{
    public function __construct(private Router $router) {}

    /**
     * @return LazyCollection<int, array{loc: string, lastmod: ?string}>
     */
    public function entries(): LazyCollection
    {
        $staticPages = array_values(array_filter(
            Indexing::INDEXABLE_ROUTES,
            fn (string $name): bool => $this->router->getRoutes()->getByName($name)?->parameterNames() === [],
        ));

        return LazyCollection::make(array_map(
            fn (string $name): array => ['loc' => CanonicalUrl::route($name), 'lastmod' => null],
            $staticPages,
        ))->concat(
            CarListing::query()
                ->publiclyVisible()
                ->select(['id', 'updated_at'])
                ->lazyById()
                ->map(fn (CarListing $listing): array => $listing->toSitemapArray()),
        );
    }
}
