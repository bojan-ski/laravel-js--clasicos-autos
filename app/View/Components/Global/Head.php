<?php

namespace App\View\Components\Global;

use App\Support\Seo\PageSeo;
use App\Support\Seo\SeoHead;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\View\Component;

class Head extends Component
{
    public string $documentTitle;

    /**
     * @var list<string>
     */
    public array $seoTags;

    public function __construct(SeoHead $seoHead, Request $request, ?string $title = null, ?PageSeo $seo = null)
    {
        $this->documentTitle = $title === null ? __('app.meta.default_title') : $title.' — '.__('app.name');
        $this->seoTags = $seoHead->render($seo ?? PageSeo::defaults(), $this->documentTitle, $request);
    }

    public function render(): View
    {
        return view('components.global.head');
    }
}
