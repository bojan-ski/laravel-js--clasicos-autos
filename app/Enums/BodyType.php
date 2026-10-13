<?php

namespace App\Enums;

use App\Contracts\ListingOption;
use Illuminate\Support\Str;

enum BodyType: string implements ListingOption
{
    case Coupe = 'Coupe';
    case Sedan = 'Sedan';
    case Convertible = 'Convertible';
    case Wagon = 'Wagon';

    public function label(): string
    {
        $key = Str::snake($this->name);

        return __("app.listings.options.body_type.{$key}");
    }
}
