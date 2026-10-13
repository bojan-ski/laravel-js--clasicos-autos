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

    public function test_the_policy_is_auto_discovered_for_the_user_model(): void
    {
        $this->assertInstanceOf(UserPolicy::class, Gate::getPolicyFor(User::class));
    }
}
