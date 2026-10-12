<?php

namespace App\Support;

use App\Enums\NavAudience;
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

            $links[] = new NavLink(
                key: $item->key,
                label: __($item->labelKey),
                url: route($item->route),
                active: $this->router->currentRouteNamed($item->activePattern),
                count: $this->countFor($item, $user),
                countStyle: $item->countStyle,
            );
        }

        return $links;
    }

    /**
     * @return list<HeaderAction>
     */
    public function actionsFor(NavAudience $audience, ?User $user): array
    {
        return [];
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
