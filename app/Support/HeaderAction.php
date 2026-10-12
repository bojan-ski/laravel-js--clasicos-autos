<?php

namespace App\Support;

use App\Enums\HeaderActionStyle;

final readonly class HeaderAction
{
    /**
     * @param  'GET'|'POST'  $method
     */
    public function __construct(
        public string $key,
        public string $label,
        public string $url,
        public string $method,
        public HeaderActionStyle $style,
        public ?string $ariaLabel = null,
    ) {}
}
