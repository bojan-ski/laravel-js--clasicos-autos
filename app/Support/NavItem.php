<?php

namespace App\Support;

use App\Contracts\NavCounter;
use App\Enums\NavAudience;
use App\Enums\NavCountStyle;
use InvalidArgumentException;

final readonly class NavItem
{
    /**
     * @param  string|list<string>  $activePattern
     * @param  list<NavAudience>  $audiences
     * @param  class-string<NavCounter>|null  $counter
     */
    public function __construct(
        public string $key,
        public string $labelKey,
        public string $route,
        public string|array $activePattern,
        public array $audiences,
        public ?string $counter = null,
        public ?NavCountStyle $countStyle = null,
        public ?string $countLabelKey = null,
    ) {
        if (($counter === null) !== ($countStyle === null)) {
            throw new InvalidArgumentException("Nav item [{$key}] must set both a counter and a count style, or neither.");
        }

        if ($countLabelKey !== null && $counter === null) {
            throw new InvalidArgumentException("Nav item [{$key}] cannot set a count label without a counter.");
        }
    }

    public function isFor(NavAudience $audience): bool
    {
        return in_array($audience, $this->audiences, true);
    }
}
