<?php

namespace App\Enums;

use App\Contracts\ListingOption;
use Illuminate\Support\Str;

enum Transmission: string implements ListingOption
{
    case Manual = 'Manual';
    case Automatic = 'Automatic';

    public function label(): string
    {
        $key = Str::snake($this->name);

        return __("app.listings.options.transmission.{$key}");
    }
}
