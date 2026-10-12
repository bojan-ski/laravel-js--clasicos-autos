<?php

namespace Tests\Feature\ReferenceData;

use App\Models\CarMaker;
use App\Models\CarModel;
use App\Models\User;
use App\Policies\CarMakerPolicy;
use App\Policies\CarModelPolicy;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ReferenceDataPolicyTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @return array<string, array{string, string}>
     */
    public static function abilities(): array
    {
        return [
            'maker viewAny' => ['viewAny', 'maker class'],
            'maker create' => ['create', 'maker class'],
            'maker update' => ['update', 'maker'],
            'maker delete' => ['delete', 'maker'],
            'model create' => ['create', 'model class'],
            'model update' => ['update', 'model'],
            'model delete' => ['delete', 'model'],
        ];
    }

    #[DataProvider('abilities')]
    public function test_a_guest_is_denied(string $ability, string $target): void
    {
        $argument = $this->argumentFor($target);

        $allowed = Gate::forUser(null)->allows($ability, $argument);

        $this->assertFalse($allowed);
    }

    #[DataProvider('abilities')]
    public function test_an_app_user_is_denied(string $ability, string $target): void
    {
        $user = User::factory()->create();
        $argument = $this->argumentFor($target);

        $allowed = Gate::forUser($user)->allows($ability, $argument);

        $this->assertFalse($allowed);
    }

    #[DataProvider('abilities')]
    public function test_an_admin_is_allowed(string $ability, string $target): void
    {
        $admin = User::factory()->admin()->create();
        $argument = $this->argumentFor($target);

        $allowed = Gate::forUser($admin)->allows($ability, $argument);

        $this->assertTrue($allowed);
    }

    public function test_an_admin_cannot_delete_a_maker_that_has_models(): void
    {
        $admin = User::factory()->admin()->create();
        $maker = CarModel::factory()->create()->maker()->sole();

        $allowed = Gate::forUser($admin)->allows('delete', $maker);

        $this->assertFalse($allowed);
    }

    public function test_the_policies_are_auto_discovered(): void
    {
        $this->assertInstanceOf(CarMakerPolicy::class, Gate::getPolicyFor(CarMaker::class));
        $this->assertInstanceOf(CarModelPolicy::class, Gate::getPolicyFor(CarModel::class));
    }

    /**
     * @return class-string|CarMaker|CarModel
     */
    private function argumentFor(string $target): string|CarMaker|CarModel
    {
        return match ($target) {
            'maker class' => CarMaker::class,
            'maker' => CarMaker::factory()->create(),
            'model class' => CarModel::class,
            'model' => CarModel::factory()->create(),
        };
    }
}
