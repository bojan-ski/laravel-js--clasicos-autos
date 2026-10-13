<?php

namespace Tests\Feature\Listings;

use App\Models\CarListing;
use App\Models\User;
use App\Policies\CarListingPolicy;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CarListingPolicyTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const array VIEWERS = ['guest', 'owner', 'stranger', 'suspended', 'admin'];

    /**
     * @return array<string, array{string, string, string, int|string}>
     */
    public static function matrix(): array
    {
        $ownerOrAdmin = [
            'pending' => [403, 'allow', 404, 404, 'allow'],
            'approved' => [403, 'allow', 403, 403, 'allow'],
        ];
        $adminOnly = [
            'pending' => [403, 403, 404, 404, 'allow'],
            'approved' => [403, 403, 403, 403, 'allow'],
        ];
        $table = [
            'view' => [
                'pending' => [404, 'allow', 404, 404, 'allow'],
                'approved' => ['allow', 'allow', 'allow', 'allow', 'allow'],
            ],
            'compare' => [
                'pending' => [404, 404, 404, 404, 404],
                'approved' => ['allow', 'allow', 'allow', 'allow', 'allow'],
            ],
            'update' => $ownerOrAdmin,
            'delete' => $ownerOrAdmin,
            'approve' => $adminOnly,
            'reject' => $adminOnly,
            'bookmark' => [
                'pending' => [403, 403, 404, 404, 403],
                'approved' => [403, 403, 'allow', 'allow', 'allow'],
            ],
            'message' => [
                'pending' => [403, 403, 404, 404, 403],
                'approved' => [403, 403, 'allow', 403, 403],
            ],
        ];

        $cases = [];
        foreach ($table as $ability => $rows) {
            $rows += ['rejected' => $rows['pending'], 'suspended_owner' => $rows['pending']];

            foreach ($rows as $listing => $outcomes) {
                foreach (array_combine(self::VIEWERS, $outcomes) as $viewer => $outcome) {
                    $cases["{$ability} {$listing} as {$viewer}"] = [$ability, $listing, $viewer, $outcome];
                }
            }
        }

        return $cases;
    }

    #[DataProvider('matrix')]
    public function test_each_ability_answers_per_viewer_and_listing_state(string $ability, string $listingState, string $viewerKind, int|string $expected): void
    {
        [$listing, $viewer] = $this->arrange($listingState, $viewerKind);

        $response = Gate::forUser($viewer)->inspect($ability, $listing);

        $this->assertSame($expected, $response->allowed() ? 'allow' : ($response->status() ?? 403));
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function creators(): array
    {
        return [
            'guest' => ['guest', false],
            'active app user' => ['stranger', true],
            'suspended app user' => ['suspended', false],
            'admin' => ['admin', false],
        ];
    }

    #[DataProvider('creators')]
    public function test_only_an_active_app_user_may_create_a_listing(string $viewerKind, bool $allowed): void
    {
        $viewer = $this->viewer($viewerKind, User::factory()->create());

        $result = Gate::forUser($viewer)->allows('create', CarListing::class);

        $this->assertSame($allowed, $result);
    }

    public function test_the_policy_is_auto_discovered(): void
    {
        $policy = Gate::getPolicyFor(CarListing::class);

        $this->assertInstanceOf(CarListingPolicy::class, $policy);
    }

    /**
     * @return array{CarListing, User|null}
     */
    private function arrange(string $listingState, string $viewerKind): array
    {
        $owner = $listingState === 'suspended_owner' ? User::factory()->suspended()->create() : User::factory()->create();
        $factory = CarListing::factory()->for($owner, 'owner');

        $created = match ($listingState) {
            'pending' => $factory->create(),
            'approved', 'suspended_owner' => $factory->approved()->create(),
            'rejected' => $factory->rejected()->create(),
        };

        $listing = CarListing::query()->findOrFail($created->id);
        $listing->preventsLazyLoading = true;

        return [$listing, $this->viewer($viewerKind, $owner)];
    }

    private function viewer(string $viewerKind, User $owner): ?User
    {
        return match ($viewerKind) {
            'guest' => null,
            'owner' => $owner,
            'stranger' => User::factory()->create(),
            'suspended' => User::factory()->suspended()->create(),
            'admin' => User::factory()->admin()->create(),
        };
    }
}
