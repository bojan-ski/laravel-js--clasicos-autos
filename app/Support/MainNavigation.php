<?php

namespace App\Support;

use App\Enums\HeaderActionStyle;
use App\Enums\NavAudience;
use App\Enums\NavCountStyle;
use App\Models\User;
use Illuminate\Contracts\Container\Container;
use Illuminate\Routing\Router;

class MainNavigation
{
    public function __construct(
        private Container $container,
        private Router $router,
    ) {}

    /**
     * @return list<NavItem>
     */
    public function items(): array
    {
        return [
            new NavItem('home', 'app.nav.home', 'home', 'home', [NavAudience::Guest, NavAudience::Member]),
            new NavItem('listings', 'app.nav.listings', 'listings.index', ['listings.index', 'listings.advanced', 'listings.show'], [NavAudience::Guest, NavAudience::Member]),
            new NavItem('compare', 'app.nav.compare', 'compare.show', 'compare.*', [NavAudience::Guest, NavAudience::Member], CompareCounter::class, NavCountStyle::Selected),
            new NavItem('bookmarks', 'app.nav.bookmarks', 'bookmarks.index', 'bookmarks.*', [NavAudience::Member]),
            new NavItem('users', 'app.nav.users', 'admin.users', 'admin.users', [NavAudience::Admin]),
            new NavItem('admin-listings', 'app.nav.listings', 'admin.listings', 'admin.listings', [NavAudience::Admin], PendingListingsCounter::class, NavCountStyle::Queue, 'app.header.count_labels.pending'),
            new NavItem('admin-reports', 'app.nav.reports', 'admin.reports', 'admin.reports', [NavAudience::Admin], OpenReportsCounter::class, NavCountStyle::Queue, 'app.header.count_labels.open'),
            new NavItem('conversations', 'app.nav.conversations', 'conversations.index', 'conversations.*', [NavAudience::Member, NavAudience::Admin], UnreadMessagesCounter::class, NavCountStyle::Unread),
        ];
    }

    /**
     * @return list<NavLink>
     */
    public function linksFor(NavAudience $audience, ?User $user): array
    {
        $links = [];

        foreach ($this->items() as $item) {
            if (! $item->isFor($audience)) {
                continue;
            }

            $count = $this->countFor($item, $user);

            $links[] = new NavLink(
                key: $item->key,
                label: __($item->labelKey),
                url: route($item->route),
                active: $this->router->currentRouteNamed(...(array) $item->activePattern),
                count: $count,
                countStyle: $item->countStyle,
                countLabel: $count !== null && $item->countLabelKey !== null ? __($item->countLabelKey) : null,
            );
        }

        return $links;
    }

    /**
     * @return list<HeaderAction>
     */
    public function actionsFor(NavAudience $audience, ?User $user): array
    {
        $logOut = new HeaderAction('log-out', __('app.header.log_out'), route('logout'), 'POST', HeaderActionStyle::Ghost);

        return match ($audience) {
            NavAudience::Guest => [
                new HeaderAction('sign-in', __('app.header.sign_in'), route('login'), 'GET', HeaderActionStyle::Secondary),
                new HeaderAction('register', __('app.header.register'), route('register'), 'GET', HeaderActionStyle::Primary),
            ],
            NavAudience::Member => [
                new HeaderAction('list-car', __('app.header.list_car'), route('listings.create'), 'GET', HeaderActionStyle::Primary),
                new HeaderAction('profile', $user?->initials() ?? '', route('profile'), 'GET', HeaderActionStyle::Avatar, __('app.header.profile')),
                $logOut,
            ],
            NavAudience::Admin => [
                new HeaderAction('account', __('app.header.account'), route('admin.account'), 'GET', HeaderActionStyle::Secondary),
                $logOut,
            ],
        };
    }

    /**
     * @return list<NavLink>
     */
    public function legalLinks(): array
    {
        $routes = [
            'privacy' => 'legal.privacy',
            'terms' => 'legal.terms',
            'cookies' => 'legal.cookies',
        ];

        $links = [];

        foreach ($routes as $key => $route) {
            $links[] = new NavLink(
                key: $key,
                label: __("app.legal.links.{$key}"),
                url: route($route),
                active: $this->router->currentRouteNamed($route),
            );
        }

        return $links;
    }

    private function countFor(NavItem $item, ?User $user): ?int
    {
        if ($item->counter === null) {
            return null;
        }

        $count = $this->container->make($item->counter)->count($user);

        return $count > 0 ? $count : null;
    }
}
