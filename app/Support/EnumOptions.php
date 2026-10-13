<?php

namespace App\Support;

use App\Contracts\ListingOption;
use BackedEnum;

final class EnumOptions
{
    /**
     * @param  class-string<ListingOption&BackedEnum>  $enum
     * @return array<string, string>
     */
    public static function for(string $enum): array
    {
        $options = [];

        foreach ($enum::cases() as $case) {
            $options[(string) $case->value] = $case->label();
        }

        return $options;
    }
}
