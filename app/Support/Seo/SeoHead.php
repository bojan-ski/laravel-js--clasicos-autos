<?php

namespace App\Support\Seo;

use Illuminate\Http\Request;

final class SeoHead
{
    public function __construct(private Indexing $indexing) {}

    /**
     * @return list<string>
     */
    public function render(PageSeo $seo, string $documentTitle, Request $request): array
    {
        $indexable = $this->indexing->isIndexable($request);
        $image = $seo->image ?? SeoImage::shareImage();
        $imageUrl = CanonicalUrl::for($image->url);
        $canonical = CanonicalUrl::for($request->url());

        $tags = [
            $this->meta('name', 'description', $seo->description),
            $this->meta('property', 'og:site_name', __('app.name')),
            $this->meta('property', 'og:type', 'website'),
            $this->meta('property', 'og:locale', config('seo.locale')),
            $this->meta('property', 'og:title', $documentTitle),
            $this->meta('property', 'og:description', $seo->description),
        ];

        if ($indexable) {
            $tags[] = $this->meta('property', 'og:url', $canonical);
        }

        array_push(
            $tags,
            $this->meta('property', 'og:image', $imageUrl),
            $this->meta('property', 'og:image:width', (string) $image->width),
            $this->meta('property', 'og:image:height', (string) $image->height),
            $this->meta('property', 'og:image:alt', $image->alt),
            $this->meta('name', 'twitter:card', 'summary_large_image'),
            $this->meta('name', 'twitter:title', $documentTitle),
            $this->meta('name', 'twitter:description', $seo->description),
            $this->meta('name', 'twitter:image', $imageUrl),
            $this->meta('name', 'theme-color', config('seo.theme_color')),
        );

        if ($seo->preloadImageUrl !== null) {
            $tags[] = '<link rel="preload" as="image" href="'.e($seo->preloadImageUrl).'" fetchpriority="high">';
        }

        if ($indexable) {
            $tags[] = '<link rel="canonical" href="'.e($canonical).'">';
            $tags[] = '<script type="application/ld+json">'.$this->structuredData($seo).'</script>';
        }

        return $tags;
    }

    private function meta(string $attribute, string $key, string $content): string
    {
        return '<meta '.$attribute.'="'.e($key).'" content="'.e($content).'">';
    }

    private function structuredData(PageSeo $seo): string
    {
        $home = CanonicalUrl::home();

        $graph = [
            [
                '@type' => 'Organization',
                '@id' => $home.'#organization',
                'name' => __('app.name'),
                'url' => $home,
                'logo' => CanonicalUrl::for(asset(config('seo.logo'))),
            ],
            [
                '@type' => 'WebSite',
                '@id' => $home.'#website',
                'name' => __('app.name'),
                'url' => $home,
                'publisher' => ['@id' => $home.'#organization'],
            ],
        ];

        if ($seo->breadcrumbs !== []) {
            $graph[] = [
                '@type' => 'BreadcrumbList',
                'itemListElement' => array_map(fn (array $crumb, int $index): array => [
                    '@type' => 'ListItem',
                    'position' => $index + 1,
                    'name' => $crumb['name'],
                    'item' => $crumb['url'],
                ], $seo->breadcrumbs, array_keys($seo->breadcrumbs)),
            ];
        }

        return json_encode(
            ['@context' => 'https://schema.org', '@graph' => [...$graph, ...$seo->structuredData]],
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        );
    }
}
