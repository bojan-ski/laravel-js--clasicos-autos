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
        $previous = $this->url->previous();

        if (parse_url($previous, PHP_URL_HOST) === $this->request->getHost() && $previous !== $this->request->fullUrl()) {
            return $previous;
        }

        return $fallback ?? route('home');
    }
}
