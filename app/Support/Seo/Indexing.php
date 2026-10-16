<?php

namespace App\Support\Seo;

use App\Models\CarListing;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;

final class Indexing
{
    /**
     * @var list<string>
     */
    public const array INDEXABLE_ROUTES = [
        'home',
        'listings.index',
        'listings.show',
        'legal.privacy',
        'legal.terms',
        'legal.cookies',
    ];

    /**
     * @var list<string>
     */
    public const array DISALLOWED_PATHS = [
        '/admin',
        '/bookmarks',
        '/compare',
        '/contact',
        '/conversations',
        '/forgot-password',
        '/listings/advanced',
        '/listings/create',
        '/login',
        '/profile',
        '/register',
        '/reset-password',
        '/users',
    ];

    public function isEnabled(): bool
    {
        return (bool) config('seo.indexable');
    }

    public function isIndexable(Request $request, int $status = 200): bool
    {
        $route = $request->route();

        if (! $route instanceof Route || ! in_array($route->getName(), self::INDEXABLE_ROUTES, true)) {
            return false;
        }

        if ($status !== 200) {
            return false;
        }

        if ($route->getName() !== 'listings.show') {
            return true;
        }

        $listing = $route->parameter('listing');

        return $listing instanceof CarListing && $listing->isPublic();
    }

    public function robotsDirective(Request $request, int $status = 200): ?string
    {
        if (! $this->isEnabled()) {
            return 'noindex, nofollow';
        }

        return $this->isIndexable($request, $status) ? null : 'noindex';
    }

    public function robotsTxt(): string
    {
        if (! $this->isEnabled()) {
            return "User-agent: *\nDisallow: /\n";
        }

        $lines = [
            'User-agent: *',
            ...array_map(fn (string $path): string => "Disallow: {$path}", self::DISALLOWED_PATHS),
            '',
            'Sitemap: '.CanonicalUrl::route('sitemap'),
        ];

        return implode("\n", $lines)."\n";
    }
}
