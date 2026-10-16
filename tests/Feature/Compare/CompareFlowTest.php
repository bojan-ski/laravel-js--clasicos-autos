<?php

namespace Tests\Feature\Compare;

use App\Models\CarListing;
use App\Models\User;
use App\Services\CompareService;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CompareFlowTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @return array<string, array{string}>
     */
    public static function viewers(): array
    {
        return [
            'guest' => ['guest'],
            'app user' => ['appUser'],
            'admin' => ['admin'],
        ];
    }

    #[DataProvider('viewers')]
    public function test_a_viewer_adds_a_public_listing_and_returns_to_the_listings_fallback(string $viewer): void
    {
        $listing = CarListing::factory()->approved()->create();
        $this->actAs($viewer);

        $response = $this->post(route('compare.add', $listing));

        $response->assertRedirect(route('listings.index'))
            ->assertSessionHas('success', __('app.compare.flash.added'));
        $this->assertSame([$listing->id], $this->held());
    }

    #[DataProvider('viewers')]
    public function test_a_viewer_removes_a_held_listing_and_returns_to_the_compare_fallback(string $viewer): void
    {
        [$first, $second] = CarListing::factory()->approved()->count(2)->create();
        $this->actAs($viewer);
        $this->hold([$first->id, $second->id]);

        $response = $this->delete(route('compare.remove', $first));

        $response->assertRedirect(route('compare.show'))
            ->assertSessionHas('success', __('app.compare.flash.removed'));
        $this->assertSame([$second->id], $this->held());
    }

    #[DataProvider('viewers')]
    public function test_a_viewer_clears_the_comparison_and_returns_to_the_compare_fallback(string $viewer): void
    {
        $listings = CarListing::factory()->approved()->count(2)->create();
        $this->actAs($viewer);
        $this->hold($listings->modelKeys());

        $response = $this->delete(route('compare.clear'));

        $response->assertRedirect(route('compare.show'))
            ->assertSessionHas('success', __('app.compare.flash.cleared'));
        $this->assertNull($this->held());
    }

    public function test_adds_keep_the_order_they_were_made_in(): void
    {
        [$older, $newer] = CarListing::factory()->approved()->count(2)->create();
        $this->hold([$newer->id]);

        $this->post(route('compare.add', $older));

        $this->assertSame([$newer->id, $older->id], $this->held());
    }

    public function test_a_third_listing_is_refused_with_the_error_flash(): void
    {
        [$first, $second, $third] = CarListing::factory()->approved()->count(3)->create();
        $this->hold([$first->id, $second->id]);

        $response = $this->post(route('compare.add', $third));

        $response->assertSessionHas('error', __('app.compare.flash.full'))
            ->assertSessionMissing('success');
        $this->assertSame([$first->id, $second->id], $this->held());
    }

    public function test_a_listing_already_held_is_refused_with_the_error_flash(): void
    {
        $listing = CarListing::factory()->approved()->create();
        $this->hold([$listing->id]);

        $response = $this->post(route('compare.add', $listing));

        $response->assertSessionHas('error', __('app.compare.flash.full'));
        $this->assertSame([$listing->id], $this->held());
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function nonPublicAdds(): array
    {
        $cases = [];

        foreach (['pending', 'rejected', 'suspended owner'] as $state) {
            foreach (['guest', 'owner', 'admin'] as $viewer) {
                if ($state === 'suspended owner' && $viewer === 'owner') {
                    continue;
                }

                $cases["{$state} as {$viewer}"] = [$state, $viewer];
            }
        }

        return $cases;
    }

    #[DataProvider('nonPublicAdds')]
    public function test_adding_a_non_public_listing_is_not_found_for_every_viewer(string $state, string $viewer): void
    {
        $owner = $state === 'suspended owner' ? User::factory()->suspended()->create() : User::factory()->create();
        $factory = CarListing::factory()->for($owner, 'owner');
        $listing = match ($state) {
            'pending' => $factory->create(),
            'rejected' => $factory->rejected()->create(),
            'suspended owner' => $factory->approved()->create(),
        };
        $held = CarListing::factory()->approved()->create();
        match ($viewer) {
            'guest' => null,
            'owner' => $this->actingAs($owner),
            'admin' => $this->actingAs(User::factory()->admin()->create()),
        };
        $this->hold([$held->id]);

        $response = $this->post(route('compare.add', $listing));

        $response->assertNotFound();
        $this->assertSame([$held->id], $this->held());
    }

    public function test_a_held_listing_that_stopped_being_public_frees_its_slot(): void
    {
        $kept = CarListing::factory()->approved()->create();
        $hidden = CarListing::factory()->rejected()->create();
        $added = CarListing::factory()->approved()->create();
        $this->hold([$kept->id, $hidden->id]);

        $response = $this->post(route('compare.add', $added));

        $response->assertSessionHas('success', __('app.compare.flash.added'));
        $this->assertSame([$kept->id, $added->id], $this->held());
    }

    public function test_a_held_listing_that_stopped_being_public_can_still_be_removed(): void
    {
        $hidden = CarListing::factory()->rejected()->create();
        $this->hold([$hidden->id]);

        $response = $this->delete(route('compare.remove', $hidden));

        $response->assertSessionHas('success', __('app.compare.flash.removed'));
        $this->assertSame([], $this->held());
    }

    public function test_removing_a_listing_that_is_not_held_changes_nothing_and_flashes_success(): void
    {
        [$held, $other] = CarListing::factory()->approved()->count(2)->create();
        $this->hold([$held->id]);

        $response = $this->delete(route('compare.remove', $other));

        $response->assertSessionHas('success', __('app.compare.flash.removed'))
            ->assertSessionMissing('error');
        $this->assertSame([$held->id], $this->held());
    }

    public function test_clearing_an_empty_comparison_flashes_the_same_message(): void
    {
        $response = $this->delete(route('compare.clear'));

        $response->assertRedirect(route('compare.show'))
            ->assertSessionHas('success', __('app.compare.flash.cleared'));
    }

    /**
     * @return array<string, array{string, string, bool}>
     */
    public static function writes(): array
    {
        return [
            'add' => ['POST', 'compare.add', true],
            'remove' => ['DELETE', 'compare.remove', true],
            'clear' => ['DELETE', 'compare.clear', false],
        ];
    }

    #[DataProvider('writes')]
    public function test_each_write_returns_to_a_same_host_referer(string $method, string $name, bool $takesListing): void
    {
        $listing = CarListing::factory()->approved()->create();
        $referer = route('listings.index', ['search' => 'bel air']);

        $response = $this->call($method, route($name, $takesListing ? $listing : []), server: $this->transformHeadersToServerVars(['Referer' => $referer]));

        $response->assertRedirect($referer);
    }

    public function test_a_non_numeric_listing_key_is_not_a_route(): void
    {
        $response = $this->post('/compare/abc');

        $response->assertNotFound();
    }

    public function test_no_compare_request_writes_to_the_database(): void
    {
        [$first, $second] = CarListing::factory()->approved()->count(2)->create();
        $writes = [];
        DB::listen(function (QueryExecuted $query) use (&$writes): void {
            if (! str_starts_with(strtolower(ltrim($query->sql)), 'select')) {
                $writes[] = $query->sql;
            }
        });

        $this->post(route('compare.add', $first));
        $this->post(route('compare.add', $second));
        $this->get(route('compare.show'));
        $this->delete(route('compare.remove', $first));
        $this->delete(route('compare.clear'));

        $this->assertSame([], $writes);
    }

    public function test_a_json_add_answers_with_the_new_state_and_count_instead_of_redirecting(): void
    {
        [$held, $listing] = CarListing::factory()->approved()->count(2)->create();
        $this->hold([$held->id]);

        $this->postJson(route('compare.add', $listing))
            ->assertOk()
            ->assertExactJson(['selected' => true, 'count' => 2, 'message' => __('app.compare.flash.added')])
            ->assertSessionMissing('success');
        $this->assertSame([$held->id, $listing->id], $this->held());
    }

    public function test_a_json_add_past_the_limit_is_a_conflict_with_the_full_message(): void
    {
        $listings = CarListing::factory()->approved()->count(3)->create();
        $this->hold([$listings[0]->id, $listings[1]->id]);

        $this->postJson(route('compare.add', $listings[2]))
            ->assertConflict()
            ->assertExactJson(['message' => __('app.compare.flash.full')])
            ->assertSessionMissing('error');
        $this->assertSame([$listings[0]->id, $listings[1]->id], $this->held());
    }

    public function test_a_json_remove_answers_with_the_new_state_and_count(): void
    {
        [$first, $second] = CarListing::factory()->approved()->count(2)->create();
        $this->hold([$first->id, $second->id]);

        $this->deleteJson(route('compare.remove', $first))
            ->assertOk()
            ->assertExactJson(['selected' => false, 'count' => 1, 'message' => __('app.compare.flash.removed')])
            ->assertSessionMissing('success');
        $this->assertSame([$second->id], $this->held());
    }

    public function test_a_json_add_of_a_non_public_listing_is_not_found(): void
    {
        $listing = CarListing::factory()->create();

        $this->postJson(route('compare.add', $listing))->assertNotFound();
        $this->assertNull($this->held());
    }

    private function actAs(string $viewer): void
    {
        match ($viewer) {
            'guest' => null,
            'appUser' => $this->actingAs(User::factory()->create()),
            'admin' => $this->actingAs(User::factory()->admin()->create()),
        };
    }

    /**
     * @param  list<int>  $ids
     */
    private function hold(array $ids): void
    {
        $this->withSession([CompareService::SESSION_KEY => $ids]);
    }

    /**
     * @return list<int>|null
     */
    private function held(): ?array
    {
        return $this->app['session.store']->get(CompareService::SESSION_KEY);
    }
}
