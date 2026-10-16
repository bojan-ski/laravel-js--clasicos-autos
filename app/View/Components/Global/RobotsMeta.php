<?php

namespace App\View\Components\Global;

use App\Support\Seo\Indexing;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\View\Component;

class RobotsMeta extends Component
{
    public ?string $directive;

    public function __construct(Indexing $indexing, Request $request, int $status = 200)
    {
        $this->directive = $indexing->robotsDirective($request, $status);
    }

    public function shouldRender(): bool
    {
        return $this->directive !== null;
    }

    public function render(): View
    {
        return view('components.global.robots-meta');
    }
}
