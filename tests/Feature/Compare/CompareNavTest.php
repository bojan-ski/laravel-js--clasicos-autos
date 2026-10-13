<?php

namespace Tests\Feature\Compare;

use App\Models\CarListing;
use App\Models\User;
use App\Services\CompareService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CompareNavTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    /**
     * @return array<string, array{bool}>
     */
    public static function audiences(): array
    {
        return [
            'guest' => [false],
            'member' => [true],
        ];
    }

    #[DataProvider('audiences')]
    public function test_the_mobile_menu_counts_two_held_listings_and_the_desktop_link_shows_none(bool $signedIn): void
    {
        $listings = CarListing::factory()->approved()->count(2)->create();
        if ($signedIn) {
            $this->actingAs(User::factory()->create());
        }
        $this->withSession([CompareService::SESSION_KEY => $listings->modelKeys()]);

        $response = $this->get(route('home'));

        $response->assertSee(__('app.header.selected_count', ['count' => 2]));
        $this->assertSame(1, substr_count((string) $response->getContent(), 'data-nav-count'));
    }

    public function test_nothing_is_counted_while_the_comparison_is_empty(): void
    {
        $response = $this->get(route('home'));

        $response->assertSee(__('app.nav.compare'))
            ->assertDontSee('data-nav-count', false);
    }

    public function test_only_public_held_listings_are_counted(): void
    {
        $public = CarListing::factory()->approved()->create();
        $hidden = CarListing::factory()->approved()->for(User::factory()->suspended(), 'owner')->create();
        $this->withSession([CompareService::SESSION_KEY => [$public->id, $hidden->id]]);

        $response = $this->get(route('home'));

        $response->assertSee(__('app.header.selected_count', ['count' => 1]))
            ->assertDontSee(__('app.header.selected_count', ['count' => 2]));
    }

    public function test_the_compare_link_is_current_on_the_compare_page(): void
    {
        $response = $this->get(route('compare.show'));

        $this->assertMatchesRegularExpression('/<a href="'.preg_quote(route('compare.show'), '/').'"\s+aria-current="page"/', (string) $response->getContent());
    }

    public function test_the_admin_header_has_no_compare_link(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $response = $this->get(route('compare.show'));

        $response->assertSee('data-header-variant="admin"', false)
            ->assertDontSee('<a href="'.route('compare.show').'"', false);
    }
}
