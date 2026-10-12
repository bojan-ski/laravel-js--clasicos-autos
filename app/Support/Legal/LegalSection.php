<?php

namespace App\Support\Legal;

use InvalidArgumentException;

final readonly class LegalSection
{
    /**
     * @param  list<string>|null  $items
     */
    public function __construct(
        public string $anchor,
        public string $heading,
        public ?array $items = null,
        public ?string $paragraph = null,
    ) {
        if (($items === null) === ($paragraph === null)) {
            throw new InvalidArgumentException("Legal section [{$anchor}] needs exactly one of items or paragraph.");
        }
    }
}
