<?php

namespace Tests\Support;

use Illuminate\Routing\Route;
use LogicException;

final class RouteParameterMap
{
    /**
     * @var array<string, array<string, string>>
     */
    private const array MAP = [
        'password.reset' => ['token' => 'junk-token', 'email' => 'someone@example.com'],
    ];

    /**
     * @return array<string, string>
     */
    public static function parametersFor(Route $route): array
    {
        if ($route->parameterNames() === []) {
            return [];
        }

        $name = (string) $route->getName();

        if (! array_key_exists($name, self::MAP)) {
            throw new LogicException("Route [{$name}] has parameters but no entry in RouteParameterMap.");
        }

        return self::MAP[$name];
    }
}
