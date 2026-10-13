<?php

namespace App\Enums;

use App\Contracts\ListingOption;
use Illuminate\Support\Str;

enum RestorationHistory: string implements ListingOption
{
    case FullyRestored = 'Fully Restored';
    case PartiallyRestored = 'Partially Restored';
    case BarnFind = 'Barn Find';
    case UnrestoredOriginal = 'Unrestored Original';

    public function label(): string
    {
        $key = Str::snake($this->name);

        return __("app.listings.options.restoration_history.{$key}");
    }
}
