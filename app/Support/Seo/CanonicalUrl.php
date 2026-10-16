<?php

namespace App\Support\Seo;

final class CanonicalUrl
{
    public static function home(): string
    {
        return self::base().'/';
    }

    public static function for(string $url): string
    {
        $path = rtrim((string) parse_url($url, PHP_URL_PATH), '/');

        return self::base().($path === '' ? '/' : $path);
    }

    public static function route(string $name, mixed $parameters = []): string
    {
        return self::for(route($name, $parameters));
    }

    private static function base(): string
    {
        return rtrim((string) config('app.url'), '/');
    }
}
