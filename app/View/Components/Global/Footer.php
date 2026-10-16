<?php

namespace App\View\Components\Global;

use App\Support\MainNavigation;
use App\Support\NavLink;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class Footer extends Component
{
    /**
     * @var list<NavLink>
     */
    public array $legalLinks;

    public string $copyright;

    public function __construct(MainNavigation $navigation)
    {
        $this->legalLinks = $navigation->legalLinks();
        $this->copyright = __('app.footer.copyright', ['year' => now()->year]);
    }

    public function render(): View
    {
        return view('components.global.footer');
    }
}
