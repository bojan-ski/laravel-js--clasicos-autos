<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Routing\UrlGenerator;

final class BackUrl
{
    public function __construct(
        private Request $request,
        private UrlGenerator $url,
    ) {}

    public function resolve(?string $fallback = null): string
    {
        $fallback ??= route('home');
        $previous = $this->url->previous($fallback);

        if (in_array(parse_url($previous, PHP_URL_SCHEME), ['http', 'https'], true)
            && parse_url($previous, PHP_URL_HOST) === $this->request->getHost()
            && $previous !== $this->request->fullUrl()) {
            return $previous;
        }

        return $fallback;
    }
}
