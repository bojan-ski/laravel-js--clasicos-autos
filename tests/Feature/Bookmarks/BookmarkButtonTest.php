<?php

namespace Tests\Feature\Bookmarks;

use App\Models\CarListing;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BookmarkButtonTest extends TestCase
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
    public function test_a_card_offers_a_signed_in_stranger_an_unpressed_add_button(string $page): void
    {
        $listing = CarListing::factory()->approved()->create();

        $form = $this->bookmarkForm((string) $this->actingAs(User::factory()->create())->get(route($page))->getContent(), $listing);

        $this->assertNotNull($form);
        $this->assertStringContainsString('aria-pressed="false"', $form);
        $this->assertStringNotContainsString('name="_method"', $form);
        $this->assertStringContainsString('fill="none"', $form);
    }

    public function test_cards_on_the_bookmarks_page_carry_pressed_remove_buttons(): void
    {
        $user = User::factory()->create();
        $listing = CarListing::factory()->approved()->create();
        $user->bookmarks()->attach($listing->id);

        $form = $this->bookmarkForm((string) $this->actingAs($user)->get(route('bookmarks.index'))->getContent(), $listing);

        $this->assertNotNull($form);
        $this->assertStringContainsString('aria-pressed="true"', $form);
    }

    public function test_a_bookmarked_listing_shows_a_pressed_remove_button_with_the_filled_icon(): void
    {
        $user = User::factory()->create();
        $listing = CarListing::factory()->approved()->create();
        $user->bookmarks()->attach($listing->id);

        $form = $this->bookmarkForm((string) $this->actingAs($user)->get(route('listings.show', $listing))->getContent(), $listing);

        $this->assertNotNull($form);
        $this->assertStringContainsString('aria-pressed="true"', $form);
        $this->assertStringContainsString('name="_method" value="DELETE"', $form);
        $this->assertStringContainsString('fill="currentColor"', $form);
    }

    public function test_the_accessible_name_is_the_same_whether_bookmarked_or_not(): void
    {
        $user = User::factory()->create();
        [$saved, $free] = CarListing::factory()->approved()->count(2)->create();
        $user->bookmarks()->attach($saved->id);

        $html = (string) $this->actingAs($user)->get(route('listings.index'))->getContent();

        $this->assertStringContainsString('<span class="sr-only">'.e(__('app.bookmarks.button.label', ['name' => $saved->name])).'</span>', (string) $this->bookmarkForm($html, $saved));
        $this->assertStringContainsString('<span class="sr-only">'.e(__('app.bookmarks.button.label', ['name' => $free->name])).'</span>', (string) $this->bookmarkForm($html, $free));
    }

    public function test_a_guest_sees_no_bookmark_button_on_a_card(): void
    {
        $listing = CarListing::factory()->approved()->create();

        $html = (string) $this->get(route('listings.index'))->getContent();

        $this->assertNull($this->bookmarkForm($html, $listing));
    }

    public function test_the_owner_sees_no_bookmark_button_on_their_own_card(): void
    {
        $owner = User::factory()->create();
        $own = CarListing::factory()->approved()->for($owner, 'owner')->create();
        $other = CarListing::factory()->approved()->create();

        $html = (string) $this->actingAs($owner)->get(route('listings.index'))->getContent();

        $this->assertNull($this->bookmarkForm($html, $own));
        $this->assertNotNull($this->bookmarkForm($html, $other));
    }

    /**
     * @return array<string, array{string, string, bool}>
     */
    public static function detailViews(): array
    {
        return [
            'approved as guest' => ['approved', 'guest', false],
            'approved as its owner' => ['approved', 'owner', false],
            'approved as a stranger' => ['approved', 'stranger', true],
            'approved as admin' => ['approved', 'admin', true],
            'pending as its owner' => ['pending', 'owner', false],
            'pending as admin' => ['pending', 'admin', false],
            'rejected as its owner' => ['rejected', 'owner', false],
            'rejected as admin' => ['rejected', 'admin', false],
            'suspended owner as admin' => ['suspended owner', 'admin', false],
        ];
    }

    #[DataProvider('detailViews')]
    public function test_the_detail_page_offers_the_button_only_to_a_non_owner_on_a_public_listing(string $state, string $viewer, bool $shown): void
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
            'stranger' => $this->actingAs(User::factory()->create()),
            'admin' => $this->actingAs(User::factory()->admin()->create()),
        };

        $html = (string) $this->get(route('listings.show', $listing))->getContent();

        $this->assertSame($shown, $this->bookmarkForm($html, $listing) !== null);
    }

    #[DataProvider('cardPages')]
    public function test_the_card_button_follows_the_compare_button(string $page): void
    {
        $listing = CarListing::factory()->approved()->create();

        $response = $this->actingAs(User::factory()->create())->get(route($page));

        $response->assertSeeInOrder([route('compare.add', $listing), route('bookmarks.store', $listing)], false);
    }

    public function test_the_detail_button_sits_between_the_compare_button_and_the_price_stamp(): void
    {
        $listing = CarListing::factory()->approved()->create();

        $response = $this->actingAs(User::factory()->create())->get(route('listings.show', $listing));

        $response->assertSeeInOrder([route('compare.add', $listing), route('bookmarks.store', $listing), '<div class="hidden shrink-0 md:block">'], false);
    }

    /**
     * @return array<string, array{bool}>
     */
    public static function signedInViewers(): array
    {
        return [
            'app user holding bookmarks' => [false],
            'admin holding bookmarks' => [true],
        ];
    }

    #[DataProvider('signedInViewers')]
    public function test_the_index_query_count_does_not_grow_with_the_cards(bool $admin): void
    {
        $user = $admin ? User::factory()->admin()->create() : User::factory()->create();
        $this->actingAs($user);
        $listings = CarListing::factory()->count(2)->approved()->create();
        $user->bookmarks()->attach($listings[0]->id);
        $two = $this->queriesFor(route('listings.index'));

        $more = CarListing::factory()->count(10)->approved()->create();
        $user->bookmarks()->attach($more->modelKeys());
        $twelve = $this->queriesFor(route('listings.index'));

        $this->assertSame($two, $twelve);
    }

    public function test_the_detail_query_count_does_not_grow_with_the_stored_bookmarks(): void
    {
        $user = User::factory()->create();
        $listing = CarListing::factory()->approved()->create();
        $this->actingAs($user);
        $none = $this->queriesFor(route('listings.show', $listing));

        $user->bookmarks()->attach(CarListing::factory()->count(20)->approved()->create()->modelKeys());
        $twenty = $this->queriesFor(route('listings.show', $listing));

        $this->assertSame($none, $twenty);
    }

    public function test_a_page_with_no_bookmark_button_never_reads_the_bookmarks(): void
    {
        $owner = User::factory()->create();
        CarListing::factory()->approved()->for($owner, 'owner')->count(2)->create();
        $this->actingAs($owner);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get(route('listings.index'))->assertOk();
        $queries = array_column(DB::getQueryLog(), 'query');
        DB::disableQueryLog();

        $this->assertSame([], array_values(array_filter($queries, fn (string $sql): bool => str_contains($sql, 'user_bookmarks'))));
    }

    private function bookmarkForm(string $html, CarListing $listing): ?string
    {
        $pattern = '/<form method="POST" action="'.preg_quote(route('bookmarks.store', $listing), '/').'"[^>]*>(.*?)<\/form>/s';

        return preg_match($pattern, $html, $matches) === 1 ? $matches[1] : null;
    }

    private function queriesFor(string $url): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get($url)->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }
}
