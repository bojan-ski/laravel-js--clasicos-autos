<?php

namespace App\Support\Seo;

final readonly class SeoImage
{
    public function __construct(
        public string $url,
        public int $width,
        public int $height,
        public string $alt,
    ) {}

    public static function shareImage(): self
    {
        return new self(
            url: asset(config('seo.image.path')),
            width: config('seo.image.width'),
            height: config('seo.image.height'),
            alt: __('app.seo.share_image_alt'),
        );
    }
}
