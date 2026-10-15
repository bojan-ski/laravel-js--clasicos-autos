<?php

namespace App\Support;

use App\Enums\NavCountStyle;

final readonly class NavLink
{
    public function __construct(
        public string $key,
        public string $label,
        public string $url,
        public bool $active,
        public ?int $count = null,
        public ?NavCountStyle $countStyle = null,
        public ?string $countLabel = null,
    ) {}
}
