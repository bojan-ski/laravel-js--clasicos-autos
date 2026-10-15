<?php

namespace App\View\Components\Global;

use App\Enums\NavAudience;
use App\Enums\NavCountStyle;
use App\Support\HeaderAction;
use App\Support\MainNavigation;
use App\Support\NavLink;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\Component;

class Header extends Component
{
    public NavAudience $audience;

    /**
     * @var list<NavLink>
     */
    public array $links;

    /**
     * @var list<HeaderAction>
     */
    public array $actions;

    /**
     * @var list<NavLink>
     */
    public array $legalLinks;

    public bool $menuSignal;

    public ?string $liveChannel;

    public function __construct(MainNavigation $navigation, ?NavAudience $audience = null)
    {
        $user = Auth::user();

        $this->liveChannel = $user?->isActiveAppUser() ? "user.{$user->id}" : null;
        $this->audience = $audience ?? NavAudience::forUser($user);
        $this->links = $navigation->linksFor($this->audience, $user);
        $this->actions = $navigation->actionsFor($this->audience, $user);
        $this->legalLinks = $navigation->legalLinks();
        $this->menuSignal = collect($this->links)
            ->contains(fn (NavLink $link): bool => $link->countStyle === NavCountStyle::Unread && $link->count !== null);
    }

    public function render(): View
    {
        return view('components.global.header');
    }
}
