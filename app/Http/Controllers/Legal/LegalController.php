<?php

namespace App\Http\Controllers\Legal;

use App\Http\Controllers\Controller;
use App\Support\BackUrl;
use App\Support\Legal\LegalDocument;
use Illuminate\Contracts\View\View;

class LegalController extends Controller
{
    public function privacy(BackUrl $backUrl): View
    {
        return $this->show('privacy', $backUrl);
    }

    public function terms(BackUrl $backUrl): View
    {
        return $this->show('terms', $backUrl);
    }

    public function cookies(BackUrl $backUrl): View
    {
        return $this->show('cookies', $backUrl);
    }

    private function show(string $key, BackUrl $backUrl): View
    {
        return view('pages.legal.show', [
            'document' => LegalDocument::fromLang($key),
            'backUrl' => $backUrl->resolve(),
        ]);
    }
}
