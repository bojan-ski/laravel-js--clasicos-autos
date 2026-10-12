<?php

namespace App\Http\Controllers\Legal;

use App\Http\Controllers\Controller;
use App\Support\CookieConsent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CookieConsentController extends Controller
{
    public function __invoke(Request $request): Response|RedirectResponse
    {
        $response = $request->expectsJson()
            ? response()->noContent()
            : back(fallback: route('home'));

        return $response->withCookie(CookieConsent::cookie());
    }
}
