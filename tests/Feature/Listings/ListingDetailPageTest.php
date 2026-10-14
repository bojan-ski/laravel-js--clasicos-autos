<?php

namespace Tests\Feature\Listings;

use App\Enums\BodyType;
use App\Enums\DocumentationStatus;
use App\Enums\EngineCondition;
use App\Enums\EngineHistory;
use App\Enums\FuelType;
use App\Enums\ListingStatus;
use App\Enums\Odometer;
use App\Enums\RestorationHistory;
use App\Enums\Transmission;
use App\Enums\VehicleCondition;
use App\Models\CarListing;
use App\Models\CarMaker;
use App\Models\CarModel;
use App\Models\ListingImage;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ListingDetailPageTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_the_details_card_shows_every_row_in_order_and_format(): void
    {
        $maker = CarMaker::factory()->create(['name' => 'Chevrolet']);
        $carModel = CarModel::factory()->for($maker, 'maker')->create(['name' => 'Bel Air', 'year_from' => 1957, 'year_to' => 1957]);
        $listing = CarListing::factory()->approved()->forCarModel($carModel)->create([
            'mileage' => 123456,
            'transmission' => Transmission::Manual,
            'fuel_type' => FuelType::Gasoline,
            'engine_size' => '4.6L',
            'engine_type' => 'V8',
            'location_city' => 'Havana',
            'location_state' => 'La Habana',
            'location_zipcode' => '10400',
            'created_at' => Carbon::create(2026, 3, 5),
        ]);

        $rows = $this->rows($this->show($listing));

        $this->assertSame([
            'Make|Chevrolet',
            'Model|Bel Air',
            'Year|1957',
            'Mileage|123,456 km',
            'Transmission|'.Transmission::Manual->label(),
            'Fuel Type|'.FuelType::Gasoline->label(),
            'Engine|4.6L V8',
            'Location|Havana, La Habana (10400)',
            'Posted:|05 March 2026',
        ], array_slice($rows, 0, 9));
    }

    /**
     * @return array<string, array{string|null, string|null, string|null}>
     */
    public static function engines(): array
    {
        return [
            'size only' => ['4.6L', null, 'Engine|4.6L'],
            'type only' => [null, 'V8', 'Engine|V8'],
            'neither' => [null, null, null],
        ];
    }

    #[DataProvider('engines')]
    public function test_the_engine_row_shows_whichever_part_is_set_and_is_absent_without_both(?string $size, ?string $type, ?string $expected): void
    {
        $listing = CarListing::factory()->approved()->create(['engine_size' => $size, 'engine_type' => $type]);

        $rows = $this->rows($this->show($listing));

        if ($expected === null) {
            $this->assertNotContains('Engine', $this->labels($rows));
        } else {
            $this->assertContains($expected, $rows);
        }
    }

    public function test_exterior_and_interior_shows_only_the_filled_fields_with_capitalised_colours(): void
    {
        $listing = CarListing::factory()->approved()->create([
            'body_type' => BodyType::Coupe,
            'exterior_color' => 'turquoise and white',
            'interior_color' => null,
            'exterior_condition' => VehicleCondition::Good,
            'interior_condition' => null,
            'seat_material' => null,
        ]);

        $rows = $this->rows($this->show($listing));

        $this->assertContains('Body Type:|'.BodyType::Coupe->label(), $rows);
        $this->assertContains('Exterior Color:|Turquoise and white', $rows);
        $this->assertContains('Condition (Exterior):|'.VehicleCondition::Good->label(), $rows);
        $this->assertSame([], array_intersect(['Interior Color:', 'Condition (Interior):', 'Seat Material:'], $this->labels($rows)));
    }

    public function test_exterior_and_interior_is_omitted_when_every_field_is_empty(): void
    {
        $listing = CarListing::factory()->approved()->create([
            'body_type' => null,
            'exterior_color' => null,
            'interior_color' => null,
            'exterior_condition' => null,
            'interior_condition' => null,
            'seat_material' => null,
        ]);

        $this->show($listing)
            ->assertDontSee(__('app.listings.show.specs.exterior_interior'))
            ->assertSee(__('app.listings.show.specs.engine_and_history'));
    }

    public function test_engine_and_history_always_shows_its_required_rows_and_zero_original_parts(): void
    {
        $listing = CarListing::factory()->approved()->create([
            'engine_history' => EngineHistory::OriginalEngine,
            'engine_condition' => EngineCondition::Running,
            'odometer' => Odometer::Original,
            'restoration_history' => RestorationHistory::FullyRestored,
            'original_parts_percentage' => 0,
            'license_plate_type' => null,
            'documentation_status' => DocumentationStatus::OriginalPapers,
        ]);

        $rows = $this->rows($this->show($listing));

        $this->assertContains('Engine History:|'.EngineHistory::OriginalEngine->label(), $rows);
        $this->assertContains('Engine Condition:|'.EngineCondition::Running->label(), $rows);
        $this->assertContains('Odometer:|'.Odometer::Original->label(), $rows);
        $this->assertContains('Restoration:|'.RestorationHistory::FullyRestored->label(), $rows);
        $this->assertContains('Original Parts:|0%', $rows);
        $this->assertContains('Documentation:|'.DocumentationStatus::OriginalPapers->label(), $rows);
        $this->assertNotContains('License Plate Type:', $this->labels($rows));
    }

    public function test_original_parts_is_absent_when_unknown(): void
    {
        $listing = CarListing::factory()->approved()->create(['original_parts_percentage' => null]);

        $this->assertNotContains('Original Parts:', $this->labels($this->rows($this->show($listing))));
    }

    public function test_the_owner_card_shows_contact_details_to_a_guest_and_counts_approved_listings_only(): void
    {
        $owner = User::factory()->create([
            'username' => 'marta',
            'email' => 'marta@example.com',
            'phone_number' => '+5355512345',
            'created_at' => Carbon::create(2024, 1, 9),
        ]);
        $listing = CarListing::factory()->approved()->for($owner, 'owner')->create();
        CarListing::factory()->approved()->for($owner, 'owner')->create();
        CarListing::factory()->for($owner, 'owner')->create();
        CarListing::factory()->rejected()->for($owner, 'owner')->create();

        $response = $this->show($listing);

        $response->assertSeeInOrder([__('app.listings.show.owner.kicker'), 'marta']);
        $this->assertSame([
            'Email|marta@example.com',
            'Phone|+5355512345',
            'Joined|09 January 2024',
            'Total listings|2',
        ], array_values(array_filter(
            $this->rows($response),
            fn (string $row): bool => in_array(explode('|', $row)[0], ['Email', 'Phone', 'Joined', 'Total listings'], true),
        )));
    }

    /**
     * @return array<string, array{string, int, int, int}>
     */
    public static function viewerActions(): array
    {
        return [
            'guest gets the login link in the card and the bar' => ['guest', 2, 0, 0],
            'owner is told it is their listing, bar is price only' => ['owner', 0, 1, 0],
            'active app user gets send message in the card and the bar' => ['app user', 0, 0, 2],
            'suspended app user gets no action' => ['suspended', 0, 0, 0],
            'admin gets no action' => ['admin', 0, 0, 0],
        ];
    }

    #[DataProvider('viewerActions')]
    public function test_the_owner_card_and_mobile_bar_render_the_viewers_action(string $viewer, int $logins, int $ownNotes, int $sendButtons): void
    {
        $owner = User::factory()->create();
        $listing = CarListing::factory()->approved()->for($owner, 'owner')->create();

        $html = $this->show($listing, $this->viewer($viewer, $owner))->getContent();

        $this->assertSame($logins, preg_match_all('/<a href="'.preg_quote(route('login'), '/').'"[^>]*>'.preg_quote(__('app.listings.show.owner.login'), '/').'<\/a>/', $html));
        $this->assertSame($ownNotes, substr_count($html, __('app.listings.show.owner.own')));
        $this->assertSame($sendButtons, preg_match_all('/<button[^>]*\sdisabled(="disabled")?>'.__('app.listings.show.owner.send_message').'<\/button>/', $html));
    }

    /**
     * @return array<string, array{string, ListingStatus, int}>
     */
    public static function seeAllViewers(): array
    {
        return [
            'guest on a public listing' => ['guest', ListingStatus::Approved, 0],
            'owner on a public listing' => ['owner', ListingStatus::Approved, 0],
            'owner on a pending listing' => ['owner', ListingStatus::Pending, 0],
            'app user on a public listing' => ['app user', ListingStatus::Approved, 1],
            'suspended app user on a public listing' => ['suspended', ListingStatus::Approved, 1],
            'admin on a public listing' => ['admin', ListingStatus::Approved, 1],
            'admin on a pending listing' => ['admin', ListingStatus::Pending, 1],
            'admin on a rejected listing' => ['admin', ListingStatus::Rejected, 1],
        ];
    }

    #[DataProvider('seeAllViewers')]
    public function test_the_owner_card_links_signed_in_non_owners_to_the_sellers_listings(string $viewer, ListingStatus $status, int $links): void
    {
        $owner = User::factory()->create(['username' => 'jdoe']);
        $listing = $this->listingWithStatus($status, $owner);

        $html = $this->show($listing, $this->viewer($viewer, $owner))->getContent();

        $this->assertSame($links, preg_match_all('/<a href="'.preg_quote(route('users.listings', $owner), '/').'"[^>]*>'.preg_quote(e(__('app.listings.show.owner.see_all', ['username' => 'jdoe'])), '/').'<\/a>/', $html));
    }

    public function test_an_admins_see_all_link_on_a_suspended_owners_listing_opens_that_sellers_page(): void
    {
        $owner = User::factory()->suspended()->create(['username' => 'jdoe']);
        $listing = CarListing::factory()->approved()->for($owner, 'owner')->create();

        $this->show($listing, User::factory()->admin()->create())
            ->assertSee('href="'.route('users.listings', $owner).'"', false);
        $this->get(route('users.listings', $owner))->assertOk();
    }

    /**
     * @return array<string, array{string, ListingStatus}>
     */
    public static function panelViewersAndStatuses(): array
    {
        $cases = [];

        foreach (['owner', 'admin'] as $viewer) {
            foreach (ListingStatus::cases() as $status) {
                $cases["{$viewer} × {$status->value}"] = [$viewer, $status];
            }
        }

        return $cases;
    }

    #[DataProvider('panelViewersAndStatuses')]
    public function test_the_status_panel_stamps_every_status_for_the_owner_and_admins(string $viewer, ListingStatus $status): void
    {
        $owner = User::factory()->create();
        $listing = $this->listingWithStatus($status, $owner);

        $this->show($listing, $this->viewer($viewer, $owner))
            ->assertSeeInOrder(['aria-label="'.__('app.listings.show.status.label').'"', $status->label()], false);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function nonModerators(): array
    {
        return [
            'guest' => ['guest'],
            'other app user' => ['app user'],
            'suspended app user' => ['suspended'],
        ];
    }

    #[DataProvider('nonModerators')]
    public function test_nobody_else_sees_the_status_panel(string $viewer): void
    {
        $owner = User::factory()->create();
        $listing = CarListing::factory()->approved()->for($owner, 'owner')->create();

        $this->show($listing, $this->viewer($viewer, $owner))
            ->assertDontSee(__('app.listings.show.status.label'));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function listingManagers(): array
    {
        return ['owner' => ['owner'], 'admin' => ['admin']];
    }

    #[DataProvider('listingManagers')]
    public function test_the_owner_and_an_admin_see_edit_and_the_delete_dialog_in_the_back_link_row(string $viewer): void
    {
        $owner = User::factory()->create();
        $listing = CarListing::factory()->approved()->for($owner, 'owner')->create();

        $html = $this->show($listing, $this->viewer($viewer, $owner))->getContent();

        $this->assertMatchesRegularExpression('/<div class="ml-auto flex flex-wrap items-center justify-end gap-2">\s*<a href="'.preg_quote(route('listings.edit', $listing), '/').'"[^>]*border-rule[^>]*>\s*Edit listing\s*<\/a>/', $html);
        $this->assertMatchesRegularExpression('/<button type="button"[^>]*border-red-deep[^>]*data-dialog-open="delete-listing" commandfor="delete-listing" command="show-modal">\s*Delete\s*<\/button>/', $html);
        $this->assertMatchesRegularExpression('/<dialog id="delete-listing" aria-labelledby="delete-listing-title"/', $html);
        $this->assertStringContainsString('<h2 id="delete-listing-title" class="text-[26px] leading-tight text-red italic">Are you sure you want to delete the car listing ?</h2>', $html);
        $this->assertMatchesRegularExpression('/<form method="POST" action="'.preg_quote(route('listings.destroy', $listing), '/').'"[^>]*>\s*<input type="hidden" name="_token"[^>]*>\s*<input type="hidden" name="_method" value="DELETE">/', $html);
        $this->assertMatchesRegularExpression('/data-dialog-close="data-dialog-close" commandfor="delete-listing" command="close">\s*Cancel\s*<\/button>/', $html);
        $this->assertMatchesRegularExpression('/<button type="submit"[^>]*border-red-deep[^>]*>\s*Delete\s*<\/button>/', $html);
    }

    #[DataProvider('nonModerators')]
    public function test_nobody_else_sees_the_edit_or_delete_controls(string $viewer): void
    {
        $owner = User::factory()->create();
        $listing = CarListing::factory()->approved()->for($owner, 'owner')->create();

        $html = $this->show($listing, $this->viewer($viewer, $owner))->getContent();

        $this->assertStringNotContainsString(route('listings.edit', $listing), $html);
        $this->assertStringNotContainsString('delete-listing', $html);
        $this->assertStringNotContainsString('ml-auto flex flex-wrap', $html);
    }

    public function test_a_rejected_listing_shows_its_reason_in_the_panel(): void
    {
        $owner = User::factory()->create();
        $listing = CarListing::factory()->rejected('Rusted sills')->for($owner, 'owner')->create();

        $this->show($listing, $owner)
            ->assertSee(__('app.listings.show.status.reason').' Rusted sills');
    }

    /**
     * @return array<string, array{ListingStatus}>
     */
    public static function statuses(): array
    {
        return array_combine(
            array_map(fn (ListingStatus $status): string => $status->value, ListingStatus::cases()),
            array_map(fn (ListingStatus $status): array => [$status], ListingStatus::cases()),
        );
    }

    #[DataProvider('statuses')]
    public function test_the_panel_notes_a_suspended_owner_whatever_the_status(ListingStatus $status): void
    {
        $listing = $this->listingWithStatus($status, User::factory()->suspended()->create());

        $this->show($listing, User::factory()->admin()->create())
            ->assertSee(__('app.listings.show.status.owner_suspended'));
    }

    #[DataProvider('statuses')]
    public function test_a_suspended_owner_sees_the_panel_and_the_suspension_note_on_their_own_listing(ListingStatus $status): void
    {
        $owner = User::factory()->suspended()->create();
        $listing = $this->listingWithStatus($status, $owner);

        $this->show($listing, $owner)
            ->assertSeeInOrder(['aria-label="'.__('app.listings.show.status.label').'"', $status->label()], false)
            ->assertSee(__('app.listings.show.status.owner_suspended'));
    }

    public function test_the_panel_has_no_suspension_note_for_an_active_owner(): void
    {
        $owner = User::factory()->create();
        $listing = CarListing::factory()->for($owner, 'owner')->create();

        $this->show($listing, $owner)
            ->assertDontSee(__('app.listings.show.status.owner_suspended'));
    }

    public function test_a_listing_without_images_shows_the_placeholder_and_no_image(): void
    {
        $listing = CarListing::factory()->approved()->create();

        $html = $this->show($listing)->getContent();

        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringNotContainsString('data-gallery', $html);
    }

    public function test_a_single_image_renders_the_main_image_without_thumbnails(): void
    {
        $listing = CarListing::factory()->approved()->withImages(1)->create(['name' => 'Bel Air']);
        $image = $listing->images()->sole();

        $this->show($listing)
            ->assertSee('src="'.$image->url().'" alt="Bel Air" width="1600" height="900"', false)
            ->assertDontSee('data-gallery-thumb', false);
    }

    public function test_eight_images_render_a_thumbnail_link_per_image_in_position_order(): void
    {
        $listing = CarListing::factory()->approved()->create(['name' => 'Bel Air']);
        foreach ([7, 6, 5, 4, 3, 2, 1, 0] as $position) {
            ListingImage::factory()->for($listing, 'listing')->create(['position' => $position]);
        }
        $images = $listing->images()->get();

        $response = $this->show($listing);

        $response->assertSee('data-gallery-main src="'.$images[0]->url().'"', false);
        $response->assertSeeInOrder($images->map(fn (ListingImage $image): string => 'href="'.$image->url().'" data-gallery-thumb')->all(), false);
        $this->assertMatchesRegularExpression('/href="'.preg_quote($images[0]->url(), '/').'" data-gallery-thumb\s+aria-current="true"/', $response->getContent());
        $this->assertMatchesRegularExpression('/src="'.preg_quote($images[7]->thumbUrl(), '/').'" alt="Bel Air, photo 8"\s+width="320" height="240"/', $response->getContent());
        $this->assertSame(8, substr_count($response->getContent(), 'data-gallery-thumb'));
        $this->assertSame(1, substr_count($response->getContent(), 'aria-current="true"'));
    }

    public function test_the_back_link_is_the_same_host_referer(): void
    {
        $listing = CarListing::factory()->approved()->create();

        $this->call('GET', route('listings.show', $listing), server: $this->transformHeadersToServerVars(['Referer' => route('listings.advanced')]))
            ->assertViewHas('backUrl', route('listings.advanced'));
    }

    /**
     * @return array<string, array{array<string, string>}>
     */
    public static function fallbackReferers(): array
    {
        return [
            'no referer' => [[]],
            'another host' => [['Referer' => 'https://evil.example.com/listings']],
            'a script url on this host' => [['Referer' => 'javascript://localhost/%0Aalert(1)']],
        ];
    }

    #[DataProvider('fallbackReferers')]
    public function test_the_back_link_falls_back_to_the_listings_index(array $headers): void
    {
        $listing = CarListing::factory()->approved()->create();

        $this->call('GET', route('listings.show', $listing), server: $this->transformHeadersToServerVars($headers))
            ->assertViewHas('backUrl', route('listings.index'));
    }

    public function test_the_title_kicker_and_price_follow_the_brief(): void
    {
        $maker = CarMaker::factory()->create(['name' => 'Chevrolet']);
        $carModel = CarModel::factory()->for($maker, 'maker')->create(['name' => 'Bel Air', 'year_from' => 1957, 'year_to' => 1957]);
        $listing = CarListing::factory()->approved()->forCarModel($carModel)->create(['name' => 'Bel Air Hardtop', 'price' => 24500]);

        $response = $this->show($listing);

        $response->assertSee('<title>Bel Air Hardtop — Clásicos Autos</title>', false);
        $response->assertSeeInOrder(['Chevrolet – Bel Air – 1957', '<h1', 'Bel Air Hardtop'], false);
        $this->assertSame(2, substr_count($response->getContent(), '$24,500'));
        $this->assertSame(2, preg_match_all('/lang="es"[^>]*>'.__('app.listings.show.price_kicker').'</', $response->getContent()));
    }

    public function test_the_listings_nav_item_is_current(): void
    {
        $listing = CarListing::factory()->approved()->create();

        $this->assertMatchesRegularExpression(
            '/href="'.preg_quote(route('listings.index'), '/').'"[^>]*aria-current="page"/',
            $this->show($listing)->getContent(),
        );
    }

    public function test_the_description_is_escaped_and_keeps_its_line_breaks(): void
    {
        $listing = CarListing::factory()->approved()->create(['description' => "First owner since 1961.\nNew <script>alert(1)</script> chrome."]);

        $this->show($listing)
            ->assertSee("First owner since 1961.\nNew &lt;script&gt;alert(1)&lt;/script&gt; chrome.", false)
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    /**
     * @return array<string, array{int}>
     */
    public static function imageCounts(): array
    {
        return [
            'one image' => [1],
            'eight images' => [8],
        ];
    }

    #[DataProvider('imageCounts')]
    public function test_the_page_runs_a_fixed_number_of_queries_whatever_the_image_count(int $count): void
    {
        $listing = CarListing::factory()->approved()->withImages($count)->create();
        $this->expectsDatabaseQueryCount(5);

        $this->show($listing);
    }

    private function show(CarListing $listing, ?User $viewer = null): TestResponse
    {
        if ($viewer !== null) {
            $this->actingAs($viewer);
        }

        return $this->get(route('listings.show', $listing))->assertViewIs('pages.listings.show');
    }

    /**
     * @return list<string>
     */
    private function rows(TestResponse $response): array
    {
        preg_match_all('/<dt[^>]*>(.*?)<\/dt>\s*<dd[^>]*>(.*?)<\/dd>/s', $response->getContent(), $matches, PREG_SET_ORDER);

        return array_map(
            fn (array $match): string => html_entity_decode($match[1], ENT_QUOTES).'|'.html_entity_decode($match[2], ENT_QUOTES),
            $matches,
        );
    }

    /**
     * @param  list<string>  $rows
     * @return list<string>
     */
    private function labels(array $rows): array
    {
        return array_map(fn (string $row): string => explode('|', $row)[0], $rows);
    }

    private function viewer(string $viewer, User $owner): ?User
    {
        return match ($viewer) {
            'guest' => null,
            'owner' => $owner,
            'app user' => User::factory()->create(),
            'suspended' => User::factory()->suspended()->create(),
            'admin' => User::factory()->admin()->create(),
        };
    }

    private function listingWithStatus(ListingStatus $status, User $owner): CarListing
    {
        $factory = CarListing::factory()->for($owner, 'owner');

        return match ($status) {
            ListingStatus::Pending => $factory->create(),
            ListingStatus::Approved => $factory->approved()->create(),
            ListingStatus::Rejected => $factory->rejected()->create(),
        };
    }
}
