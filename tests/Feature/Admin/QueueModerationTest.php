<?php

namespace Tests\Feature\Admin;

use App\Enums\ListingStatus;
use App\Models\CarListing;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class QueueModerationTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const array VIEW = ['status' => 'all', 'search' => 'bel', 'page' => 2];

    public function test_approving_from_the_queue_returns_to_the_same_queue_view(): void
    {
        $listing = CarListing::factory()->create();

        $this->moderate('approve', $listing, ['return' => 'queue', ...self::VIEW], ['version' => $listing->moderationVersion()])
            ->assertRedirect(route('admin.listings', self::VIEW))
            ->assertSessionHas('success', 'Car listing approved.');

        $this->assertSame(ListingStatus::Approved, $listing->fresh()->status);
    }

    public function test_rejecting_from_the_queue_returns_to_the_same_queue_view(): void
    {
        $listing = CarListing::factory()->create();

        $this->moderate('reject', $listing, ['return' => 'queue', ...self::VIEW], [
            'version' => $listing->moderationVersion(),
            'rejection_reason' => 'Blurry photos.',
        ])
            ->assertRedirect(route('admin.listings', self::VIEW))
            ->assertSessionHas('success', 'Car listing rejected.');

        $this->assertSame('Blurry photos.', $listing->fresh()->rejection_reason);
    }

    public function test_a_listing_that_is_no_longer_pending_returns_to_the_queue_with_the_error(): void
    {
        $listing = CarListing::factory()->approved()->create();

        $this->moderate('reject', $listing, ['return' => 'queue', ...self::VIEW], [
            'version' => $listing->moderationVersion(),
            'rejection_reason' => 'Blurry photos.',
        ])
            ->assertRedirect(route('admin.listings', self::VIEW))
            ->assertSessionHas('error', 'Only a pending listing can be approved or rejected.');

        $this->assertSame(ListingStatus::Approved, $listing->fresh()->status);
    }

    public function test_a_stale_version_returns_to_the_queue_with_the_changed_error(): void
    {
        $listing = CarListing::factory()->create();

        $this->moderate('approve', $listing, ['return' => 'queue', ...self::VIEW], ['version' => $listing->moderationVersion() - 1])
            ->assertRedirect(route('admin.listings', self::VIEW))
            ->assertSessionHas('error', 'This listing changed after you opened it. Review it again.');

        $this->assertTrue($listing->fresh()->isPending());
    }

    public function test_a_missing_reason_returns_to_the_queue_with_the_first_error_as_the_flash(): void
    {
        $listing = CarListing::factory()->create();

        $this->moderate('reject', $listing, ['return' => 'queue', ...self::VIEW], ['version' => $listing->moderationVersion()])
            ->assertRedirect(route('admin.listings', self::VIEW))
            ->assertSessionHas('error', 'The reason for rejection field is required.')
            ->assertSessionDoesntHaveErrors();

        $this->assertTrue($listing->fresh()->isPending());
    }

    public function test_hostile_queue_values_and_a_hostile_referer_are_dropped(): void
    {
        $listing = CarListing::factory()->create();
        $query = ['return' => 'queue', 'status' => 'archived', 'search' => str_repeat('a', 101), 'page' => '-3'];

        $response = $this->actingAs(User::factory()->admin()->create())->call(
            'PATCH',
            route('admin.listings.approve', [$listing, ...$query]),
            ['version' => $listing->moderationVersion()],
            server: $this->transformHeadersToServerVars(['Referer' => 'https://evil.example/']),
        );

        $response->assertRedirect(route('admin.listings'))->assertSessionHas('success');
        $this->moderate('approve', CarListing::factory()->create(), ['return' => 'queue', 'search' => '   ', 'page' => 'abc'], ['version' => 0])
            ->assertRedirect(route('admin.listings'));
    }

    public function test_without_the_exact_marker_every_outcome_lands_on_the_detail_page(): void
    {
        $listing = CarListing::factory()->create();

        $this->moderate('approve', $listing, ['return' => 'QUEUE', ...self::VIEW], ['version' => $listing->moderationVersion() - 1])
            ->assertRedirect(route('listings.show', $listing))
            ->assertSessionHas('error');
        $this->moderate('reject', $listing, self::VIEW, ['version' => $listing->moderationVersion(), 'return' => 'queue'])
            ->assertRedirect(route('listings.show', $listing))
            ->assertSessionHas('error', 'The reason for rejection field is required.');
        $this->moderate('approve', $listing, [], ['version' => $listing->moderationVersion(), 'return' => 'queue'])
            ->assertRedirect(route('listings.show', $listing))
            ->assertSessionHas('success', 'Car listing approved.');
    }

    /**
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>  $payload
     */
    private function moderate(string $action, CarListing $listing, array $query, array $payload): TestResponse
    {
        $response = $this->actingAs(User::factory()->admin()->create())
            ->patch(route("admin.listings.{$action}", [$listing, ...$query]), $payload);
        $this->app['auth']->forgetGuards();

        return $response;
    }
}
