<?php

namespace Tests\Support;

use App\Models\CarListing;
use App\Models\User;
use Database\Factories\CarListingFactory;
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

        if (in_array($name, ['listings.show', 'compare.add', 'compare.remove', 'bookmarks.store', 'bookmarks.destroy'], true)) {
            return ['listing' => (string) CarListing::factory()->approved()->create()->id];
        }

        if (in_array($name, ['listings.edit', 'listings.update', 'listings.destroy', 'listings.images.edit', 'listings.images.store'], true)) {
            return ['listing' => (string) self::listingForActor()->create()->id];
        }

        if (in_array($name, ['listings.images.primary', 'listings.images.destroy'], true)) {
            $listing = self::listingForActor()->withImages(2)->create();

            return ['listing' => (string) $listing->id, 'image' => (string) $listing->images()->where('position', 1)->sole()->id];
        }

        if ($name === 'users.listings') {
            return ['user' => (string) User::factory()->create()->id];
        }

        if (! array_key_exists($name, self::MAP)) {
            throw new LogicException("Route [{$name}] has parameters but no entry in RouteParameterMap.");
        }

        return self::MAP[$name];
    }

    private static function listingForActor(): CarListingFactory
    {
        $user = auth()->user();
        $factory = CarListing::factory()->approved();

        if ($user instanceof User && $user->isAppUser()) {
            $factory = $factory->for($user, 'owner');
        }

        return $factory;
    }
}
