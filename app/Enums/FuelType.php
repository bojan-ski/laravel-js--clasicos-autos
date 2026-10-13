<?php

namespace App\Enums;

use App\Contracts\ListingOption;
use Illuminate\Support\Str;

enum FuelType: string implements ListingOption
{
    case Gasoline = 'Gasoline';
    case Diesel = 'Diesel';

    public function label(): string
    {
        $key = Str::snake($this->name);

        return __("app.listings.options.fuel_type.{$key}");
    }
}
