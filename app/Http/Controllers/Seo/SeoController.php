<?php

namespace App\Http\Controllers\Seo;

use App\Http\Controllers\Controller;
use App\Services\SitemapService;
use App\Support\Seo\Indexing;
use Illuminate\Http\Response;

class SeoController extends Controller
{
    public function robots(Indexing $indexing): Response
    {
        return response($indexing->robotsTxt(), 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    public function sitemap(SitemapService $sitemap): Response
    {
        return response()->view('seo.sitemap', [
            'entries' => $sitemap->entries(),
        ], 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
