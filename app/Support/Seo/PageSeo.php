<?php

namespace App\Support\Seo;

use App\Support\Legal\LegalDocument;

final readonly class PageSeo
{
    /**
     * @param  list<array{name: string, url: string}>  $breadcrumbs
     * @param  list<array<string, mixed>>  $structuredData
     */
    public function __construct(
        public string $description,
        public array $breadcrumbs = [],
        public ?SeoImage $image = null,
        public ?string $preloadImageUrl = null,
        public array $structuredData = [],
    ) {}

    public static function defaults(): self
    {
        return new self(description: __('app.seo.description'));
    }

    public static function home(): self
    {
        return new self(
            description: __('app.seo.description'),
            preloadImageUrl: asset('assets/images/hero.jpg'),
        );
    }

    public static function listingsIndex(): self
    {
        return new self(
            description: __('app.seo.listings_description'),
            breadcrumbs: self::trail(['name' => __('app.nav.listings'), 'url' => CanonicalUrl::route('listings.index')]),
        );
    }

    public static function legal(LegalDocument $document, string $key): self
    {
        return new self(
            description: __("app.seo.legal.{$key}"),
            breadcrumbs: self::trail(['name' => $document->title, 'url' => CanonicalUrl::route("legal.{$key}")]),
        );
    }

    /**
     * @param  array{name: string, url: string}  ...$crumbs
     * @return list<array{name: string, url: string}>
     */
    public static function trail(array ...$crumbs): array
    {
        return [['name' => __('app.nav.home'), 'url' => CanonicalUrl::home()], ...array_values($crumbs)];
    }
}
