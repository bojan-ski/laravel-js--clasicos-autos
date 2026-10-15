<?php

namespace Tests\Support;

use Illuminate\Routing\Route;
use LogicException;

enum RouteBand: string
{
    case Public = 'public';
    case Guest = 'guest';
    case Auth = 'auth';
    case Profile = 'profile';
    case Seller = 'seller';
    case Admin = 'admin';
    case Messaging = 'messaging';

    public static function fromRoute(Route $route): self
    {
        $middleware = $route->gatherMiddleware();

        if (! in_array('web', $middleware, true)) {
            throw new LogicException("Route [{$route->getName()}] is outside the web middleware group.");
        }

        $extra = array_values(array_diff($middleware, ['web']));
        sort($extra);

        foreach (self::cases() as $band) {
            if ($extra === $band->middleware()) {
                return $band;
            }
        }

        throw new LogicException("Route [{$route->getName()}] has middleware [".implode(', ', $extra).'] that matches no band.');
    }

    /**
     * @return list<string>
     */
    public function middleware(): array
    {
        return match ($this) {
            self::Public => [],
            self::Guest => ['guest'],
            self::Auth => ['auth'],
            self::Profile => ['auth', 'can:access-profile'],
            self::Seller => ['auth', 'can:access-profile', 'can:create,App\Models\CarListing'],
            self::Admin => ['auth', 'can:access-admin'],
            self::Messaging => ['auth', 'can:access-messaging'],
        };
    }
}
