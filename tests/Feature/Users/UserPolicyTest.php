<?php

namespace Tests\Feature\Users;

use App\Models\User;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class UserPolicyTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @return array<string, array{string, string, bool}>
     */
    public static function accessTable(): array
    {
        return [
            'active seller, viewed by the seller' => ['active', 'seller', true],
            'active seller, viewed by an admin' => ['active', 'admin', true],
            'active seller, viewed by a stranger' => ['active', 'stranger', true],
            'suspended seller, viewed by the seller' => ['suspended', 'seller', true],
            'suspended seller, viewed by an admin' => ['suspended', 'admin', true],
            'suspended seller, viewed by a stranger' => ['suspended', 'stranger', false],
            'admin id, viewed by that admin' => ['admin', 'seller', false],
            'admin id, viewed by another admin' => ['admin', 'admin', false],
            'admin id, viewed by an app user' => ['admin', 'stranger', false],
        ];
    }

    #[DataProvider('accessTable')]
    public function test_view_listings_follows_the_access_table(string $sellerState, string $viewerKind, bool $allowed): void
    {
        $seller = match ($sellerState) {
            'active' => User::factory()->create(),
            'suspended' => User::factory()->suspended()->create(),
            'admin' => User::factory()->admin()->create(),
        };
        $viewer = match ($viewerKind) {
            'seller' => $seller,
            'admin' => User::factory()->admin()->create(),
            'stranger' => User::factory()->create(),
        };

        $response = Gate::forUser($viewer)->inspect('viewListings', $seller);

        $this->assertSame($allowed, $response->allowed());
        if (! $allowed) {
            $this->assertSame(404, $response->status());
        }
    }

    public function test_a_suspended_stranger_is_treated_like_any_other_stranger(): void
    {
        $viewer = User::factory()->suspended()->create();

        $this->assertTrue(Gate::forUser($viewer)->inspect('viewListings', User::factory()->create())->allowed());
        $this->assertSame(404, Gate::forUser($viewer)->inspect('viewListings', User::factory()->suspended()->create())->status());
    }

    public function test_sees_every_listing_of_is_true_for_the_seller_and_admins_only(): void
    {
        $seller = User::factory()->create();

        $this->assertTrue($seller->seesEveryListingOf($seller));
        $this->assertTrue(User::factory()->admin()->create()->seesEveryListingOf($seller));
        $this->assertFalse(User::factory()->create()->seesEveryListingOf($seller));
        $this->assertFalse(User::factory()->suspended()->create()->seesEveryListingOf($seller));
    }

    /**
     * @return array<string, array{string, string, string, bool}>
     */
    public static function managementTable(): array
    {
        $cases = [];

        foreach (['suspend', 'unsuspend', 'delete'] as $ability) {
            $cases["{$ability}: admin on an active app user"] = [$ability, 'admin', 'app user', true];
            $cases["{$ability}: admin on a suspended app user"] = [$ability, 'admin', 'suspended app user', true];
            $cases["{$ability}: admin on another admin"] = [$ability, 'admin', 'admin', false];
            $cases["{$ability}: admin on themself"] = [$ability, 'admin', 'self', false];
            $cases["{$ability}: app user on another app user"] = [$ability, 'app user', 'app user', false];
            $cases["{$ability}: app user on themself"] = [$ability, 'app user', 'self', false];
        }

        return $cases;
    }

    #[DataProvider('managementTable')]
    public function test_only_an_admin_manages_an_app_user(string $ability, string $actorKind, string $targetKind, bool $allowed): void
    {
        $actor = $actorKind === 'admin' ? User::factory()->admin()->create() : User::factory()->create();
        $target = match ($targetKind) {
            'self' => $actor,
            'admin' => User::factory()->admin()->create(),
            'app user' => User::factory()->create(),
            'suspended app user' => User::factory()->suspended()->create(),
        };

        $response = Gate::forUser($actor)->inspect($ability, $target);

        $this->assertSame($allowed, $response->allowed());
        if (! $allowed) {
            $this->assertNull($response->status(), 'A refusal is a plain 403 deny, never denyAsNotFound.');
        }
    }

    public function test_the_policy_is_auto_discovered_for_the_user_model(): void
    {
        $this->assertInstanceOf(UserPolicy::class, Gate::getPolicyFor(User::class));
    }
}
