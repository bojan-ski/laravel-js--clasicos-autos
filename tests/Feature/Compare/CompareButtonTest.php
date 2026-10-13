<?php

namespace Tests\Feature\Compare;

use App\Models\CarListing;
use App\Models\User;
use App\Services\CompareService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CompareButtonTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    /**
     * @return array<string, array{string}>
     */
    public static function cardPages(): array
    {
        return [
            'home' => ['home'],
            'listings index' => ['listings.index'],
            'advanced search' => ['listings.advanced'],
        ];
    }

    #[DataProvider('cardPages')]
    public function test_every_listing_card_carries_an_unpressed_add_button(string $page): void
    {
        $listing = CarListing::factory()->approved()->create();

        $form = $this->compareForm((string) $this->get(route($page))->getContent(), $listing);

        $this->assertNotNull($form);
        $this->assertStringContainsString('aria-pressed="false"', $form);
        $this->assertStringNotContainsString('name="_method"', $form);
    }

    public function test_a_held_listing_shows_a_pressed_remove_button_with_the_check_badge(): void
    {
        $listing = CarListing::factory()->approved()->create();
        $this->withSession([CompareService::SESSION_KEY => [$listing->id]]);

        $form = $this->compareForm((string) $this->get(route('listings.show', $listing))->getContent(), $listing);

        $this->assertNotNull($form);
        $this->assertStringContainsString('aria-pressed="true"', $form);
        $this->assertStringContainsString('name="_method" value="DELETE"', $form);
        $this->assertStringContainsString('d="M20 6 9 17l-5-5"', $form);
    }

    public function test_the_accessible_name_is_the_same_whether_held_or_not(): void
    {
        [$held, $free] = CarListing::factory()->approved()->count(2)->create();
        $this->withSession([CompareService::SESSION_KEY => [$held->id]]);

        $html = (string) $this->get(route('listings.index'))->getContent();

        $this->assertStringContainsString('<span class="sr-only">'.e(__('app.compare.button.label', ['name' => $held->name])).'</span>', (string) $this->compareForm($html, $held));
        $this->assertStringContainsString('<span class="sr-only">'.e(__('app.compare.button.label', ['name' => $free->name])).'</span>', (string) $this->compareForm($html, $free));
    }

    /**
     * @return array<string, array{string, string, bool}>
     */
    public static function detailViews(): array
    {
        return [
            'approved as guest' => ['approved', 'guest', true],
            'approved as its owner' => ['approved', 'owner', true],
            'approved as admin' => ['approved', 'admin', true],
            'pending as its owner' => ['pending', 'owner', false],
            'pending as admin' => ['pending', 'admin', false],
            'rejected as its owner' => ['rejected', 'owner', false],
            'rejected as admin' => ['rejected', 'admin', false],
            'suspended owner as admin' => ['suspended owner', 'admin', false],
        ];
    }

    #[DataProvider('detailViews')]
    public function test_the_detail_page_offers_the_button_only_for_a_public_listing(string $state, string $viewer, bool $shown): void
    {
        $owner = $state === 'suspended owner' ? User::factory()->suspended()->create() : User::factory()->create();
        $factory = CarListing::factory()->for($owner, 'owner');
        $listing = match ($state) {
            'approved', 'suspended owner' => $factory->approved()->create(),
            'pending' => $factory->create(),
            'rejected' => $factory->rejected()->create(),
        };
        match ($viewer) {
            'guest' => null,
            'owner' => $this->actingAs($owner),
            'admin' => $this->actingAs(User::factory()->admin()->create()),
        };

        $html = (string) $this->get(route('listings.show', $listing))->getContent();

        $this->assertSame($shown, $this->compareForm($html, $listing) !== null);
    }

    public function test_the_index_query_count_does_not_grow_with_the_cards_for_a_signed_in_viewer(): void
    {
        $this->actingAs(User::factory()->create());
        CarListing::factory()->count(2)->approved()->create();
        $two = $this->queriesFor(route('listings.index'));

        CarListing::factory()->count(10)->approved()->create();
        $twelve = $this->queriesFor(route('listings.index'));

        $this->assertSame($two, $twelve);
    }

    private function compareForm(string $html, CarListing $listing): ?string
    {
        $pattern = '/<form method="POST" action="'.preg_quote(route('compare.add', $listing), '/').'"[^>]*>(.*?)<\/form>/s';

        return preg_match($pattern, $html, $matches) === 1 ? $matches[1] : null;
    }

    private function queriesFor(string $url): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get($url);
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }
}
