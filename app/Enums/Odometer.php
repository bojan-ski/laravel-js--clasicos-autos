<?php

namespace App\Enums;

use App\Contracts\ListingOption;
use Illuminate\Support\Str;

enum Odometer: string implements ListingOption
{
    case Original = 'Original';
    case Rebuilt = 'Rebuilt';
    case Unknown = 'Unknown';

    public function label(): string
    {
        $key = Str::snake($this->name);

        return __("app.listings.options.odometer.{$key}");
    }
}
