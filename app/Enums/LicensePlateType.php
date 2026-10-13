<?php

namespace App\Enums;

use App\Contracts\ListingOption;
use Illuminate\Support\Str;

enum LicensePlateType: string implements ListingOption
{
    case OriginalPlate = 'Original Plate';
    case ModernPlate = 'Modern Plate';
    case NoPlate = 'No Plate';

    public function label(): string
    {
        $key = Str::snake($this->name);

        return __("app.listings.options.license_plate_type.{$key}");
    }
}
