<?php

namespace App\Support;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;

final class CookieConsent
{
    public const string NAME = 'cookie_consent';

    public const string VALUE = 'accepted';

    public const int MINUTES = 525600;

    public static function accepted(Request $request): bool
    {
        return $request->cookie(self::NAME) === self::VALUE;
    }

    public static function cookie(): Cookie
    {
        return cookie(self::NAME, self::VALUE, self::MINUTES);
    }
}
