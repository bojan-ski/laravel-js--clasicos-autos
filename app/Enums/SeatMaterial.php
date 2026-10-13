<?php

namespace App\Enums;

use App\Contracts\ListingOption;
use Illuminate\Support\Str;

enum SeatMaterial: string implements ListingOption
{
    case Leather = 'Leather';
    case Cloth = 'Cloth';
    case Vinyl = 'Vinyl';
    case Velour = 'Velour';

    public function label(): string
    {
        $key = Str::snake($this->name);

        return __("app.listings.options.seat_material.{$key}");
    }
}
