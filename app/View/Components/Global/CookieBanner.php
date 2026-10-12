<?php

namespace App\View\Components\Global;

use App\Support\CookieConsent;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\View\Component;

class CookieBanner extends Component
{
    public function __construct(private Request $request) {}

    public function shouldRender(): bool
    {
        return ! CookieConsent::accepted($this->request);
    }

    public function render(): View
    {
        return view('components.global.cookie-banner');
    }
}
