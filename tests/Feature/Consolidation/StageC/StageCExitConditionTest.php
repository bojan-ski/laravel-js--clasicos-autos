<?php

namespace Tests\Feature\Consolidation\StageC;

use App\Enums\ListingStatus;
use App\Enums\UserStatus;
use App\Models\CarListing;
use App\Models\User;
use App\Services\CompareService;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class StageCExitConditionTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const string REASON = 'Rusted sills under the doors.';

    private const array SELLER_LISTINGS = [
        'approved' => 'Vendedor Bel Air Aprobado',
        'pending' => 'Vendedor Bel Air Pendiente',
        'rejected' => 'Vendedor Bel Air Rechazado',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_a_guest_compares_two_listings_in_order_and_only_the_differing_rows_are_marked(): void
    {
        $first = CarListing::factory()->approved()->create(['name' => 'Compare Bel Air Uno']);
        $second = $first->replicate();
        $second->forceFill([
            'name' => 'Compare Bel Air Dos',
            'price' => $first->price + 500,
            'mileage' => $first->mileage + 1000,
            'user_id' => User::factory()->create()->id,
        ])->save();
        $pending = CarListing::factory()->create(['name' => 'Compare Bel Air Pendiente']);

        $this->post(route('compare.add', $first))->assertRedirect();
        $this->post(route('compare.add', $pending))
            ->assertNotFound()
            ->assertSee(__('app.errors.404.title'));
        $this->assertSame([$first->id], session(CompareService::SESSION_KEY));
        $this->post(route('compare.add', $second))->assertRedirect();

        $response = $this->get(route('compare.show'))
            ->assertOk()
            ->assertSeeInOrder([$first->name, $second->name])
            ->assertDontSee($pending->name);

        $xpath = $this->xpath($response);
        $differing = array_map(
            fn (DOMElement $header): string => $header->textContent,
            iterator_to_array($xpath->query('//tbody/tr[@data-differs]/th')),
        );

        $this->assertCount(2, $differing);
        $this->assertStringContainsString(__('app.compare.rows.mileage'), $differing[0]);
        $this->assertStringContainsString(__('app.compare.rows.price'), $differing[1]);
        $this->assertSame(2, $xpath->query("//tbody/tr/th/span[contains(concat(' ', normalize-space(@class), ' '), ' sr-only ')]")->length);
        $this->assertSame(4, $xpath->query("//tbody/tr/td[contains(concat(' ', normalize-space(@class), ' '), ' text-red ')]")->length);
        $this->assertSame(4, $xpath->query("//tbody/tr[@data-differs]/td[contains(concat(' ', normalize-space(@class), ' '), ' text-red ')]")->length);
    }

    public function test_a_listing_whose_owner_is_suspended_drops_out_of_the_comparison_silently(): void
    {
        $first = CarListing::factory()->approved()->create(['name' => 'Compare Bel Air Uno']);
        $second = CarListing::factory()->approved()->create(['name' => 'Compare Bel Air Dos']);
        $this->post(route('compare.add', $first));
        $this->post(route('compare.add', $second));

        $owner = $first->owner;
        $owner->status = UserStatus::Suspended;
        $owner->save();

        $this->get(route('compare.show'))
            ->assertOk()
            ->assertSee($second->name)
            ->assertDontSee($first->name)
            ->assertDontSee(__('app.compare.flash.removed'))
            ->assertDontSee(__('app.flash.error_kicker'));
        $this->assertSame([$second->id], session(CompareService::SESSION_KEY));
    }

    public function test_a_member_bookmarks_a_public_listing_but_its_owner_never_can(): void
    {
        $listing = CarListing::factory()->approved()->create(['name' => 'Bookmark Bel Air']);
        $member = User::factory()->create();

        $this->actingAs($member)->post(route('bookmarks.store', $listing))->assertRedirect();

        $this->assertSame(1, DB::table('user_bookmarks')->count());
        $this->assertTrue($member->bookmarks()->whereKey($listing->id)->exists());
        $this->get(route('bookmarks.index'))->assertOk()->assertSee($listing->name);

        $this->actingAs($listing->owner);

        foreach (['post' => 'bookmarks.store', 'delete' => 'bookmarks.destroy'] as $verb => $route) {
            $this->{$verb}(route($route, $listing))
                ->assertForbidden()
                ->assertSee(__('app.errors.403.title'));
        }

        $this->assertSame(1, DB::table('user_bookmarks')->count());
        $this->assertFalse($listing->owner->bookmarks()->exists());
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function sellerPageOutcomes(): array
    {
        $table = [
            'active' => ['seller' => 'all with stamps', 'admin' => 'all with stamps', 'stranger' => 'approved only', 'guest' => 'login'],
            'suspended' => ['seller' => 'login', 'admin' => 'all with stamps', 'stranger' => '404', 'guest' => 'login'],
            'admin' => ['seller' => '404', 'admin' => '404', 'stranger' => '404', 'guest' => 'login'],
        ];

        $cases = [];

        foreach ($table as $seller => $outcomes) {
            foreach ($outcomes as $viewer => $outcome) {
                $cases["{$seller} seller as {$viewer}"] = [$seller, $viewer, $outcome];
            }
        }

        return $cases;
    }

    #[DataProvider('sellerPageOutcomes')]
    public function test_the_seller_page_answers_each_viewer_per_seller_state(string $seller, string $viewer, string $outcome): void
    {
        $sellerUser = match ($seller) {
            'active' => User::factory()->create(),
            'suspended' => User::factory()->suspended()->create(),
            'admin' => User::factory()->admin()->create(),
        };

        if ($seller !== 'admin') {
            CarListing::factory()->approved()->for($sellerUser, 'owner')->create(['name' => self::SELLER_LISTINGS['approved']]);
            CarListing::factory()->for($sellerUser, 'owner')->create(['name' => self::SELLER_LISTINGS['pending']]);
            CarListing::factory()->rejected(self::REASON)->for($sellerUser, 'owner')->create(['name' => self::SELLER_LISTINGS['rejected']]);
        }

        match ($viewer) {
            'seller' => $this->actingAs($sellerUser),
            'admin' => $this->actingAs(User::factory()->admin()->create()),
            'stranger' => $this->actingAs(User::factory()->create()),
            'guest' => null,
        };

        $response = $this->get(route('users.listings', $sellerUser));

        match ($outcome) {
            'login' => $response->assertRedirect(route('login')),
            '404' => $response->assertNotFound()->assertSee(__('app.errors.404.title'))->assertDontSee(self::SELLER_LISTINGS['approved']),
            'approved only' => $this->assertApprovedOnly($response),
            'all with stamps' => $this->assertEveryStatusWithStamps($response),
        };
    }

    private function assertApprovedOnly(TestResponse $response): void
    {
        $response->assertOk()
            ->assertSee(self::SELLER_LISTINGS['approved'])
            ->assertDontSee(self::SELLER_LISTINGS['pending'])
            ->assertDontSee(self::SELLER_LISTINGS['rejected'])
            ->assertDontSee(ListingStatus::Pending->label())
            ->assertDontSee(ListingStatus::Rejected->label())
            ->assertDontSee(self::REASON);
    }

    private function assertEveryStatusWithStamps(TestResponse $response): void
    {
        $response->assertOk()
            ->assertSee(self::SELLER_LISTINGS['approved'])
            ->assertSeeInOrder([self::SELLER_LISTINGS['pending'], ListingStatus::Pending->label()])
            ->assertSeeInOrder([self::SELLER_LISTINGS['rejected'], ListingStatus::Rejected->label(), self::REASON]);
    }

    private function xpath(TestResponse $response): DOMXPath
    {
        $document = new DOMDocument;
        libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
        libxml_clear_errors();

        return new DOMXPath($document);
    }
}
