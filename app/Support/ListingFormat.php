<?php

namespace App\Support;

use Carbon\CarbonInterface;

final class ListingFormat
{
    public static function price(int $price): string
    {
        return '$'.number_format($price);
    }

    public static function mileage(int $mileage): string
    {
        return __('app.listings.show.specs.mileage_value', ['mileage' => number_format($mileage)]);
    }

    public static function date(CarbonInterface $date): string
    {
        return $date->format('d F Y');
    }
}
