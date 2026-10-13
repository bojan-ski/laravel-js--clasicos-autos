<?php

namespace Tests\Support;

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\Str;

final class ApplicationRoutes
{
    /**
     * @return array<string, Route>
     */
    public static function named(): array
    {
        $routes = RouteFacade::getRoutes()->getRoutesByName();
        ksort($routes);

        return array_filter($routes, fn (Route $route, string $name): bool => ! Str::startsWith($name, 'storage.'), ARRAY_FILTER_USE_BOTH);
    }
}
