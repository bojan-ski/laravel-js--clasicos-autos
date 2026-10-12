<?php

namespace App\Http\Controllers\Legal;

use App\Http\Controllers\Controller;
use App\Support\BackUrl;
use App\Support\CookieConsent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CookieConsentController extends Controller
{
    public function __invoke(Request $request, BackUrl $backUrl): Response|RedirectResponse
    {
        $response = $request->expectsJson()
            ? response()->noContent()
            : redirect($backUrl->resolve());

        return $response->withCookie(CookieConsent::cookie());
    }
}
