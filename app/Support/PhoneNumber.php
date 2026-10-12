<?php

namespace App\Support;

final class PhoneNumber
{
    public static function normalize(string $input): string
    {
        $digits = preg_replace('/\D+/', '', $input) ?? '';

        return str_starts_with(trim($input), '+') ? '+'.$digits : $digits;
    }
}
