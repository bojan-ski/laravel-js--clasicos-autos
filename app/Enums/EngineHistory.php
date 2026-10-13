<?php

namespace App\Enums;

use App\Contracts\ListingOption;
use Illuminate\Support\Str;

enum EngineHistory: string implements ListingOption
{
    case OriginalEngine = 'Original Engine';
    case ReplacedEngine = 'Replaced Engine';
    case RebuiltEngine = 'Rebuilt Engine';

    public function label(): string
    {
        $key = Str::snake($this->name);

        return __("app.listings.options.engine_history.{$key}");
    }
}
