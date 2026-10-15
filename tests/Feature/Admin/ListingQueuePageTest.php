<?php

namespace Tests\Feature\Admin;

use App\Enums\ListingQueueFilter;
use App\Models\CarListing;
use App\Models\CarMaker;
use App\Models\CarModel;
use App\Models\User;
use App\Services\ListingModerationService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class ListingQueuePageTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_the_page_renders_the_spanish_kicker_title_search_and_chips(): void
    {
        $html = $this->page()->assertViewIs('pages.admin.listings.index')->getContent();

        $this->assertMatchesRegularExpression('/lang="es"[^>]*>\s*Anuncios por revisar/', $html);
        $this->assertStringContainsString('<h1 class="text-[38px] leading-none md:text-[56px]">Listings queue</h1>', $html);
        $this->assertMatchesRegularExpression('/<form method="GET" action="'.preg_quote(route('admin.listings'), '/').'" role="search"/', $html);
        $this->assertMatchesRegularExpression('/<label for="search"[^>]*>Listing, username or email<\/label>\s*<input id="search" name="search" type="search"/', $html);
        $this->assertStringNotContainsString('name="status"', $html);
        $this->assertMatchesRegularExpression('/<nav data-brand-chips aria-label="Filter by status"/', $html);
        $this->assertMatchesRegularExpression('/<a href="'.preg_quote(route('admin.listings'), '/').'"\s*aria-current="true"[^>]*><svg[^>]*>.*?<\/svg>\s*Pending<\/a>/s', $html);
        foreach (['approved' => 'Approved', 'rejected' => 'Rejected', 'all' => 'All'] as $status => $label) {
            $this->assertMatchesRegularExpression('/<a href="'.preg_quote(route('admin.listings', ['status' => $status]), '/').'"\s*class="[^"]*border-rule[^"]*">'.$label.'<\/a>/', $html);
        }
    }

    public function test_pending_lists_only_pending_listings_oldest_submission_first_with_ties_by_id(): void
    {
        $this->travelTo('2026-10-15 10:00:00');
        $newest = CarListing::factory()->create(['submitted_at' => now()]);
        $tiedFirst = CarListing::factory()->create(['submitted_at' => now()->subDay()]);
        $tiedSecond = CarListing::factory()->create(['submitted_at' => now()->subDay()]);
        $oldest = CarListing::factory()->create(['submitted_at' => now()->subDays(3)]);
        CarListing::factory()->approved()->create(['submitted_at' => now()->subDays(9)]);
        CarListing::factory()->rejected()->create(['submitted_at' => now()->subDays(9)]);

        $this->assertSame([$oldest->id, $tiedFirst->id, $tiedSecond->id, $newest->id], $this->ids());
        $this->assertSame($this->ids(), $this->ids(['status' => 'pending']));
    }

    public function test_an_edited_approved_listing_rejoins_the_pending_queue_at_the_end(): void
    {
        $this->travelTo('2026-10-15 10:00:00');
        $resubmitted = CarListing::factory()->approved()->create(['submitted_at' => now()->subDays(10)]);
        $waiting = CarListing::factory()->create(['submitted_at' => now()->subDay()]);

        $this->travel(1)->hours();
        app(ListingModerationService::class)->returnToPending($resubmitted);

        $this->assertSame([$waiting->id, $resubmitted->id], $this->ids());
    }

    public function test_approved_rejected_and_all_list_their_statuses_newest_submission_first(): void
    {
        $this->travelTo('2026-10-15 10:00:00');
        $oldApproved = CarListing::factory()->approved()->create(['submitted_at' => now()->subDays(3)]);
        $newApproved = CarListing::factory()->approved()->create(['submitted_at' => now()->subDay()]);
        $rejected = CarListing::factory()->rejected()->create(['submitted_at' => now()->subDays(2)]);
        $pending = CarListing::factory()->create(['submitted_at' => now()]);

        $this->assertSame([$newApproved->id, $oldApproved->id], $this->ids(['status' => 'approved']));
        $this->assertSame([$rejected->id], $this->ids(['status' => 'rejected']));
        $this->assertSame([$pending->id, $newApproved->id, $rejected->id, $oldApproved->id], $this->ids(['status' => 'all']));
        $this->assertSame(ListingQueueFilter::All, $this->page(['status' => 'all'])->viewData('filter'));
    }

    public function test_listings_of_a_suspended_owner_stay_in_the_queue_with_the_suspension_after_the_owner(): void
    {
        $this->travelTo('2026-10-15 10:00:00');
        $owner = User::factory()->suspended(now()->addDays(7))->create(['username' => 'banned seller']);
        $listing = CarListing::factory()->for($owner, 'owner')->create();

        $response = $this->page();

        $this->assertSame([$listing->id], $response->viewData('listings')->pluck('id')->all());
        $this->assertMatchesRegularExpression('/>banned seller<\/a>\s*<span class="text-xs text-red-deep">Suspended until 22.10.2026<\/span>/', $response->getContent());
    }

    public function test_twelve_listings_fit_a_page_and_the_links_keep_status_and_search(): void
    {
        CarListing::factory()->approved()->count(13)->sequence(fn ($sequence) => ['name' => "Bel Air {$sequence->index}"])->create();

        $this->assertCount(12, $this->page(['status' => 'approved', 'search' => 'bel air'])->viewData('listings'));
        $this->assertCount(1, $this->page(['status' => 'approved', 'search' => 'bel air', 'page' => 2])->viewData('listings'));
        $this->page(['status' => 'approved', 'search' => 'bel air'])
            ->assertSee(e(route('admin.listings', ['status' => 'approved', 'search' => 'bel air', 'page' => 2])), false);
    }

    public function test_search_matches_the_name_the_owners_username_or_email_case_insensitively(): void
    {
        $byName = CarListing::factory()->create(['name' => 'Classic Fairlane']);
        $byUsername = CarListing::factory()->for(User::factory()->state(['username' => 'CLASSIC carla']), 'owner')->create();
        $byEmail = CarListing::factory()->for(User::factory()->state(['email' => 'classic.ernesto@example.com']), 'owner')->create();
        CarListing::factory()->create(['name' => 'Impala']);

        $ids = $this->ids(['search' => '  classic  ']);
        sort($ids);

        $this->assertSame([$byName->id, $byUsername->id, $byEmail->id], $ids);
        $this->assertMatchesRegularExpression('/<input id="search" name="search" type="search"\s*value="classic"/', $this->page(['search' => 'classic'])->getContent());
    }

    public function test_no_search_branch_leaks_a_listing_of_another_status(): void
    {
        $pending = CarListing::factory()->create(['name' => 'Zephyr pending']);
        CarListing::factory()->approved()->create(['name' => 'Zephyr approved']);
        CarListing::factory()->approved()->for(User::factory()->state(['username' => 'zephyr fan']), 'owner')->create(['name' => 'Bel Air']);
        CarListing::factory()->approved()->for(User::factory()->state(['email' => 'zephyr@example.com']), 'owner')->create(['name' => 'Impala']);

        $this->assertSame([$pending->id], $this->ids(['search' => 'zephyr']));
    }

    public function test_like_wildcards_in_the_term_match_literally(): void
    {
        $underscore = CarListing::factory()->create(['name' => 'fast_car']);
        CarListing::factory()->create(['name' => 'fastXcar']);
        $percent = CarListing::factory()->create(['name' => 'half%off']);
        CarListing::factory()->create(['name' => 'halfoff']);

        $this->assertSame([$underscore->id], $this->ids(['search' => 't_c']));
        $this->assertSame([$percent->id], $this->ids(['search' => 'f%o']));
    }

    public function test_an_invalid_query_returns_to_the_bare_queue_with_the_error(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('admin.listings', ['search' => str_repeat('a', 101)]))
            ->assertRedirect(route('admin.listings'))
            ->assertSessionHasErrors(['search' => 'The search field must not be greater than 100 characters.']);
        $this->actingAs($admin)->get(route('admin.listings', ['search' => ['a']]))
            ->assertRedirect(route('admin.listings'))
            ->assertSessionHasErrors('search');
        $this->actingAs($admin)->get(route('admin.listings', ['status' => 'archived']))
            ->assertRedirect(route('admin.listings'))
            ->assertSessionHasErrors('status');
    }

    public function test_the_search_form_keeps_a_non_pending_status(): void
    {
        $this->page(['status' => 'rejected'])->assertSee('<input type="hidden" name="status" value="rejected">', false);
    }

    public function test_only_an_empty_unsearched_pending_queue_says_so_and_every_other_empty_view_shows_no_listings_found(): void
    {
        $this->page()->assertSee('The queue is empty. Nothing to review!')->assertDontSee('No listings found.');

        CarListing::factory()->count(13)->create();

        $this->page(['search' => 'nothing-matches-this'])->assertSee('No listings found.')->assertDontSee('The queue is empty');
        $this->page(['status' => 'approved'])->assertSee('No listings found.');
        $this->page(['page' => 5])
            ->assertSee('No listings found.')
            ->assertSee(e(route('admin.listings', ['page' => 1])), false);
    }

    public function test_a_row_shows_the_photo_listing_year_owner_submission_and_status(): void
    {
        $this->travelTo('2026-10-15 10:00:00');
        $maker = CarMaker::factory()->create(['name' => 'Chevrolet']);
        $model = CarModel::factory()->for($maker, 'maker')->create(['name' => 'Bel Air', 'year_from' => 1950, 'year_to' => 1981]);
        $owner = User::factory()->create(['username' => 'carla']);
        $listing = CarListing::factory()->forCarModel($model)->for($owner, 'owner')->withImages(1)->create(['name' => 'Sunday cruiser', 'year' => 1957]);

        $html = $this->page()->getContent();

        $this->assertSeeInOrderHtml($html, ['Photo', 'Listing', 'Year', 'Owner', 'Submitted', 'Status', '<span class="sr-only">Actions</span>']);
        $this->assertStringContainsString('<img src="'.$listing->primaryImage->thumbUrl().'" alt="" width="320" height="240" loading="lazy" class="h-15 w-20 object-cover photo-grade">', $html);
        $this->assertStringContainsString('<a href="'.route('listings.show', $listing).'" class="font-heading text-red italic wrap-anywhere hover:text-red-deep">Sunday cruiser</a>', $html);
        $this->assertStringContainsString('<span class="text-xs text-ink-2 wrap-anywhere">Chevrolet · Bel Air</span>', $html);
        $this->assertStringNotContainsString('Custom model', $html);
        $this->assertStringContainsString('<a href="'.route('users.listings', $owner).'"', $html);
        $this->assertSeeInOrderHtml($html, ['1957', '>carla</a>', '15.10.2026', 'Pending']);
        $this->assertStringNotContainsString('Suspended', $html);
    }

    public function test_a_custom_model_row_is_marked_and_a_listing_without_photos_shows_the_placeholder(): void
    {
        CarListing::factory()->customModel()->create(['model' => 'Roadster']);

        $html = $this->page()->getContent();

        $this->assertMatchesRegularExpression('/· Roadster<\/span>\s*<span class="text-\[11px\] tracking-\[0.12em\] text-ink-2 uppercase">Custom model<\/span>/', $html);
        $this->assertMatchesRegularExpression('/<span class="flex h-15 w-20 items-center justify-center border border-rule bg-paper text-muted">\s*<svg/', $html);
        $this->assertStringNotContainsString('<img', $html);
    }

    public function test_every_row_stamps_its_status_and_a_rejected_row_shows_its_reason(): void
    {
        CarListing::factory()->approved()->create();
        CarListing::factory()->rejected('Blurry photos.')->create();
        CarListing::factory()->create();

        $html = $this->page(['status' => 'all'])->getContent();

        foreach (['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'] as $tone => $label) {
            $this->assertMatchesRegularExpression('/border-status-'.$tone.'[^>]*>\s*<span[^>]*>\s*<span class="block px-2 py-0.5 text-\[11px\] tracking-\[0.16em\] uppercase">'.$label.'<\/span>/', $html);
        }
        $this->assertStringContainsString('<span class="text-xs text-red-deep wrap-anywhere">Blurry photos.</span>', $html);
    }

    public function test_only_pending_rows_offer_actions_and_promote_needs_a_custom_model(): void
    {
        $listModel = CarListing::factory()->create(['name' => 'List car']);
        $custom = CarListing::factory()->customModel()->create(['name' => 'Custom car']);
        $approved = CarListing::factory()->approved()->customModel()->create(['name' => 'Approved car']);
        $rejected = CarListing::factory()->rejected()->customModel()->create(['name' => 'Rejected car']);

        $html = $this->page(['status' => 'all'])->getContent();

        foreach ([$listModel, $custom] as $listing) {
            $this->assertSame(1, substr_count($html, 'action="'.e(route('admin.listings.approve', [$listing, 'return' => 'queue', 'status' => 'all'])).'"'));
            $this->assertSame(1, substr_count($html, 'id="reject-listing-'.$listing->id.'"'));
            $this->assertSame(1, substr_count($html, 'id="reject-listing-'.$listing->id.'-reason"'));
        }
        $this->assertSame(1, substr_count($html, 'id="promote-model-'.$custom->id.'"'));
        $this->assertStringNotContainsString('promote-model-'.$listModel->id, $html);
        foreach ([$approved, $rejected] as $listing) {
            $this->assertStringNotContainsString(route('admin.listings.approve', $listing), $html);
            $this->assertStringNotContainsString('reject-listing-'.$listing->id, $html);
            $this->assertStringNotContainsString('promote-model-'.$listing->id, $html);
        }
        $this->assertStringNotContainsString('id="reject-listing"', $html);
        $this->assertStringNotContainsString('id="rejection_reason"', $html);
    }

    public function test_every_action_names_its_listing_and_posts_the_row_version(): void
    {
        $listing = CarListing::factory()->customModel()->create(['name' => 'Sunday cruiser']);
        $version = $listing->moderationVersion();

        $html = $this->page()->getContent();

        $this->assertMatchesRegularExpression('/<input type="hidden" name="version" value="'.$version.'">\s*<button type="submit"[^>]*border-red text-red[^>]*>\s*Approve<span class="sr-only"> Sunday cruiser<\/span>\s*<\/button>/', $html);
        $this->assertMatchesRegularExpression('/data-dialog-open="reject-listing-'.$listing->id.'" commandfor="reject-listing-'.$listing->id.'" command="show-modal">\s*Reject<span class="sr-only"> Sunday cruiser<\/span>/', $html);
        $this->assertMatchesRegularExpression('/data-dialog-open="promote-model-'.$listing->id.'" commandfor="promote-model-'.$listing->id.'" command="show-modal">\s*Promote to list<span class="sr-only"> Sunday cruiser<\/span>/', $html);
        $this->assertSame(3, substr_count($html, 'name="version" value="'.$version.'"'));
    }

    public function test_the_promote_dialog_prefills_the_model_and_year_and_names_the_year_to_include(): void
    {
        $maker = CarMaker::factory()->create(['name' => 'Ford']);
        $listing = CarListing::factory()->customModel()->create(['car_maker_id' => $maker->id, 'model' => 'Roadster', 'year' => 1932]);
        $id = 'promote-model-'.$listing->id;

        $html = $this->page()->getContent();

        $this->assertStringContainsString('<h2 id="'.$id.'-title" class="text-[26px] leading-tight text-red italic">Promote to the model list</h2>', $html);
        $this->assertMatchesRegularExpression('/<input id="'.$id.'-name" name="name" type="text"\s*value="Roadster"\s*required aria-required="true"\s*aria-describedby="'.$id.'-name-hint"[^>]*maxlength="64"/', $html);
        $this->assertStringContainsString('<p id="'.$id.'-name-hint" class="text-xs text-muted">Added to the Ford models. An existing model with this name is linked instead.</p>', $html);
        foreach (['year-from' => 'year_from', 'year-to' => 'year_to'] as $suffix => $name) {
            $this->assertMatchesRegularExpression('/<input id="'.$id.'-'.$suffix.'" name="'.$name.'" type="number"\s*value="1932"\s*required aria-required="true"[^>]*inputmode="numeric" min="1900" max="1989" aria-describedby="'.$id.'-years-hint"/', $html);
        }
        $this->assertStringContainsString('<p id="'.$id.'-years-hint" class="-mt-2 text-xs text-muted">The production years must include 1932.</p>', $html);
        $this->assertStringContainsString('action="'.e(route('admin.listings.promote', [$listing, 'return' => 'queue'])).'"', $html);
    }

    public function test_action_urls_carry_the_return_marker_and_the_current_view(): void
    {
        CarListing::factory()->count(12)->sequence(fn ($sequence) => ['name' => "Bel Air {$sequence->index}", 'submitted_at' => now()->subDays(20 - $sequence->index)])->create();
        $last = CarListing::factory()->customModel()->create(['name' => 'Bel Air last', 'submitted_at' => now()]);
        $query = ['return' => 'queue', 'status' => 'pending', 'search' => 'bel', 'page' => 2];

        $html = $this->page(['status' => 'pending', 'search' => 'bel', 'page' => 2])->getContent();

        foreach (['approve', 'reject', 'promote'] as $action) {
            $this->assertStringContainsString('action="'.e(route("admin.listings.{$action}", [$last, ...$query])).'"', $html);
        }
    }

    public function test_the_listings_nav_item_is_current_on_the_queue(): void
    {
        $html = $this->page()->getContent();

        $this->assertMatchesRegularExpression('/<a href="'.preg_quote(route('admin.listings'), '/').'"\s+aria-current="page"\s+class="[^"]*">\s*Listings\s*<\/a>/', $html);
        $this->assertSeeInOrderHtml($html, ['href="'.route('admin.users').'"', 'href="'.route('admin.listings').'"', 'href="'.route('conversations.index').'"']);
    }

    public function test_the_query_count_does_not_grow_with_the_rows(): void
    {
        $admin = User::factory()->admin()->create();
        $this->mixedRows(2);
        $few = $this->queriesFor($admin);

        $this->mixedRows(10);
        $many = $this->queriesFor($admin);

        $this->assertCount(12, $this->page([], $admin)->viewData('listings'));
        $this->assertSame($few, $many);
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function page(array $query = [], ?User $admin = null): TestResponse
    {
        $response = $this->actingAs($admin ?? User::factory()->admin()->create())->get(route('admin.listings', $query));
        $this->app['auth']->forgetGuards();

        return $response->assertOk();
    }

    /**
     * @param  array<string, mixed>  $query
     * @return list<int>
     */
    private function ids(array $query = []): array
    {
        return $this->page($query)->viewData('listings')->pluck('id')->all();
    }

    private function mixedRows(int $count): void
    {
        foreach (range(1, $count) as $index) {
            $factory = CarListing::factory()->withImages(1)->for(User::factory()->suspended(now()->addDays(7)), 'owner');

            ($index % 2 === 0 ? $factory->customModel() : $factory)->create();
        }
    }

    private function queriesFor(User $admin): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->page([], $admin);
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    /**
     * @param  list<string>  $needles
     */
    private function assertSeeInOrderHtml(string $html, array $needles): void
    {
        $position = 0;

        foreach ($needles as $needle) {
            $found = strpos($html, $needle, $position);
            $this->assertNotFalse($found, "Missing or out of order: {$needle}");
            $position = $found + strlen($needle);
        }
    }
}
