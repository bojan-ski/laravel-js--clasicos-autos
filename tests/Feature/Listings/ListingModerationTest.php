<?php

namespace Tests\Feature\Listings;

use App\Enums\ListingStatus;
use App\Http\Requests\Admin\ApproveListingRequest;
use App\Http\Requests\Admin\ModerateListingRequest;
use App\Http\Requests\Admin\RejectListingRequest;
use App\Models\CarListing;
use App\Models\User;
use App\Support\ListingFormValues;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ListingModerationTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Storage::fake('public');
    }

    /**
     * @return array<string, array{string}>
     */
    public static function moderationRoutes(): array
    {
        return [
            'approve' => ['admin.listings.approve'],
            'reject' => ['admin.listings.reject'],
        ];
    }

    #[DataProvider('moderationRoutes')]
    public function test_a_guest_is_sent_to_login(string $route): void
    {
        $listing = CarListing::factory()->create();
        $before = $this->snapshot($listing);

        $this->patch(route($route, $listing), $this->validPayload($route, $listing))->assertRedirect(route('login'));

        $this->assertSame($before, $this->snapshot($listing));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function refusedAppUsers(): array
    {
        $cases = [];

        foreach (['admin.listings.approve', 'admin.listings.reject'] as $route) {
            foreach (['owner', 'stranger'] as $actor) {
                $cases["{$route}, {$actor}"] = [$route, $actor];
            }
        }

        return $cases;
    }

    #[DataProvider('refusedAppUsers')]
    public function test_an_app_user_is_refused_before_validation_and_nothing_changes(string $route, string $actor): void
    {
        $owner = User::factory()->create();
        $listing = CarListing::factory()->for($owner, 'owner')->create();
        $before = $this->snapshot($listing);
        $user = $actor === 'stranger' ? User::factory()->create() : $owner;

        $this->actingAs($user)
            ->patch(route($route, $listing), [])
            ->assertForbidden()
            ->assertSessionDoesntHaveErrors();

        $this->assertSame($before, $this->snapshot($listing));
    }

    #[DataProvider('moderationRoutes')]
    public function test_an_unknown_or_non_numeric_listing_is_not_found(string $route): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->patch(route($route, 999999), ['version' => 1])->assertNotFound();
        $this->patch('/admin/listings/abc/'.Str::afterLast($route, '.'), ['version' => 1])->assertNotFound();
    }

    /**
     * @return array<string, array{class-string<ModerateListingRequest>, string, bool}>
     */
    public static function requestAuthorizations(): array
    {
        $cases = [];

        foreach ([ApproveListingRequest::class, RejectListingRequest::class] as $request) {
            foreach (['owner' => false, 'stranger' => false, 'admin' => true] as $actor => $allowed) {
                $cases[class_basename($request).", {$actor}"] = [$request, $actor, $allowed];
            }
        }

        return $cases;
    }

    /**
     * @param  class-string<ModerateListingRequest>  $requestClass
     */
    #[DataProvider('requestAuthorizations')]
    public function test_the_form_request_asks_the_policy_behind_the_admin_gate(string $requestClass, string $actor, bool $allowed): void
    {
        $owner = User::factory()->create();
        $listing = CarListing::factory()->for($owner, 'owner')->create();
        $this->actingAs(match ($actor) {
            'owner' => $owner,
            'stranger' => User::factory()->create(),
            'admin' => User::factory()->admin()->create(),
        });
        $request = $requestClass::create('/admin/listings/'.$listing->id, 'PATCH');
        $route = (new Route('PATCH', '/admin/listings/{listing}', []))->bind($request);
        $route->setParameter('listing', $listing);
        $request->setRouteResolver(fn (): Route => $route);

        $this->assertSame($allowed, $request->authorize()->allowed());
    }

    public function test_an_admin_approves_a_pending_listing_and_it_becomes_public(): void
    {
        $listing = CarListing::factory()->create(['name' => 'Bel Air Convertible']);

        $this->actingAs(User::factory()->admin()->create())
            ->patch(route('admin.listings.approve', $listing), ['version' => $listing->moderationVersion()])
            ->assertRedirect(route('listings.show', $listing))
            ->assertSessionHas('success', __('app.listings.moderation.approved'));

        $this->assertSame(ListingStatus::Approved, $listing->fresh()->status);
        $this->app['auth']->forgetGuards();
        $this->get(route('listings.show', $listing))->assertOk();
        $this->get(route('listings.index'))->assertSee('Bel Air Convertible');
    }

    public function test_an_admin_rejects_a_pending_listing_with_a_trimmed_reason(): void
    {
        $owner = User::factory()->create();
        $listing = CarListing::factory()->for($owner, 'owner')->create();

        $this->actingAs(User::factory()->admin()->create())
            ->patch(route('admin.listings.reject', $listing), [
                'version' => $listing->moderationVersion(),
                'rejection_reason' => '  The photos are too dark.  ',
            ])
            ->assertRedirect(route('listings.show', $listing))
            ->assertSessionHas('success', __('app.listings.moderation.rejected'));

        $this->assertSame(ListingStatus::Rejected, $listing->fresh()->status);
        $this->assertSame('The photos are too dark.', $listing->fresh()->rejection_reason);

        $this->app['auth']->forgetGuards();
        $this->get(route('listings.show', $listing))->assertNotFound();
        $this->actingAs(User::factory()->create())->get(route('listings.show', $listing))->assertNotFound();
        $this->actingAs($owner)->get(route('listings.show', $listing))
            ->assertOk()
            ->assertSee(__('app.listings.status.rejected'))
            ->assertSee('The photos are too dark.');
    }

    /**
     * @return array<string, array{string|null, string}>
     */
    public static function invalidReasons(): array
    {
        return [
            'missing' => [null, 'required'],
            'whitespace only' => ['    ', 'required'],
            'too long' => [str_repeat('a', 1001), 'max'],
        ];
    }

    #[DataProvider('invalidReasons')]
    public function test_an_invalid_reason_returns_to_the_listing_with_an_error(?string $reason, string $rule): void
    {
        $listing = CarListing::factory()->create();
        $before = $this->snapshot($listing);
        $attribute = __('app.listings.moderation.attributes.rejection_reason');
        $message = $rule === 'max'
            ? __('validation.max.string', ['attribute' => $attribute, 'max' => 1000])
            : __('validation.required', ['attribute' => $attribute]);

        $this->actingAs(User::factory()->admin()->create())
            ->withHeader('Referer', 'https://evil.example/')
            ->patch(route('admin.listings.reject', $listing), array_filter([
                'version' => $listing->moderationVersion(),
                'rejection_reason' => $reason,
            ], fn (?string $value): bool => $value !== null))
            ->assertRedirect(route('listings.show', $listing))
            ->assertSessionHas('error', $message);

        $this->assertSame($before, $this->snapshot($listing));
    }

    /**
     * @return array<string, array{string, string|null, string}>
     */
    public static function invalidVersions(): array
    {
        $cases = [];

        foreach (['admin.listings.approve', 'admin.listings.reject'] as $route) {
            $cases["{$route}, missing"] = [$route, null, 'required'];
            $cases["{$route}, not an integer"] = [$route, 'yesterday', 'integer'];
        }

        return $cases;
    }

    #[DataProvider('invalidVersions')]
    public function test_an_invalid_version_returns_to_the_listing_with_an_error(string $route, ?string $version, string $rule): void
    {
        $listing = CarListing::factory()->create();
        $before = $this->snapshot($listing);
        $payload = ['rejection_reason' => 'A reason.'];

        if ($version !== null) {
            $payload['version'] = $version;
        }

        $this->actingAs(User::factory()->admin()->create())
            ->patch(route($route, $listing), $payload)
            ->assertRedirect(route('listings.show', $listing))
            ->assertSessionHas('error', __("validation.{$rule}", ['attribute' => __('app.listings.moderation.attributes.version')]));

        $this->assertSame($before, $this->snapshot($listing));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function moderatedListings(): array
    {
        $cases = [];

        foreach (['admin.listings.approve', 'admin.listings.reject'] as $route) {
            foreach (['approved', 'rejected'] as $state) {
                $cases["{$route}, {$state}"] = [$route, $state];
            }
        }

        return $cases;
    }

    #[DataProvider('moderatedListings')]
    public function test_a_listing_that_is_not_pending_is_refused_with_the_guard_flash(string $route, string $state): void
    {
        $listing = CarListing::factory()->{$state}()->create();
        $before = $this->snapshot($listing);

        $this->actingAs(User::factory()->admin()->create())
            ->patch(route($route, $listing), $this->validPayload($route, $listing))
            ->assertRedirect(route('listings.show', $listing))
            ->assertSessionHas('error', __('app.listings.moderation.not_pending'));

        $this->assertSame($before, $this->snapshot($listing));
    }

    #[DataProvider('moderationRoutes')]
    public function test_a_listing_the_owner_edited_after_the_page_rendered_is_refused(string $route): void
    {
        $owner = User::factory()->create();
        $listing = CarListing::factory()->for($owner, 'owner')->create();
        $renderedVersion = $listing->moderationVersion();
        $this->travel(5)->seconds();

        $this->actingAs($owner)
            ->put(route('listings.update', $listing), [...ListingFormValues::for($listing), 'name' => 'Edited after review'])
            ->assertSessionDoesntHaveErrors();
        $before = $this->snapshot($listing);

        $this->actingAs(User::factory()->admin()->create())
            ->patch(route($route, $listing), [...$this->validPayload($route, $listing), 'version' => $renderedVersion])
            ->assertRedirect(route('listings.show', $listing))
            ->assertSessionHas('error', __('app.listings.moderation.changed'));

        $this->assertSame($before, $this->snapshot($listing));
        $this->assertSame('Edited after review', $listing->fresh()->name);
    }

    #[DataProvider('moderationRoutes')]
    public function test_a_listing_whose_photos_changed_after_the_page_rendered_is_refused(string $route): void
    {
        $owner = User::factory()->create();
        $listing = CarListing::factory()->for($owner, 'owner')->withImages(1)->create();
        $renderedVersion = $listing->moderationVersion();
        $this->travel(5)->seconds();

        $this->actingAs($owner)
            ->post(route('listings.images.store', $listing), ['images' => [UploadedFile::fake()->image('car.jpg', 120, 90)]])
            ->assertSessionDoesntHaveErrors();
        $before = $this->snapshot($listing);

        $this->actingAs(User::factory()->admin()->create())
            ->patch(route($route, $listing), [...$this->validPayload($route, $listing), 'version' => $renderedVersion])
            ->assertRedirect(route('listings.show', $listing))
            ->assertSessionHas('error', __('app.listings.moderation.changed'));

        $this->assertSame($before, $this->snapshot($listing));
        $this->assertSame(ListingStatus::Pending, $listing->fresh()->status);
    }

    public function test_posted_server_controlled_fields_are_ignored(): void
    {
        $this->freezeSecond();
        $listing = CarListing::factory()->create(['submitted_at' => now()->subDays(2)]);
        $admin = User::factory()->admin()->create();
        $other = User::factory()->admin()->create();
        $this->travel(1)->hours();

        $this->actingAs($admin)->patch(route('admin.listings.approve', $listing), [
            'version' => $listing->moderationVersion(),
            'status' => 'rejected',
            'moderated_by' => $other->id,
            'approved_at' => '2001-01-01 00:00:00',
            'rejected_at' => '2001-01-01 00:00:00',
            'submitted_at' => '2001-01-01 00:00:00',
            'updated_at' => '2001-01-01 00:00:00',
        ])->assertSessionHas('success');

        $listing->refresh();
        $this->assertSame(ListingStatus::Approved, $listing->status);
        $this->assertSame($admin->id, $listing->moderated_by);
        $this->assertEquals(now(), $listing->approved_at);
        $this->assertNull($listing->rejected_at);
        $this->assertEquals(now()->subHour()->subDays(2), $listing->submitted_at);
        $this->assertEquals(now(), $listing->updated_at);
    }

    public function test_a_suspended_owners_listing_can_be_approved_but_stays_hidden(): void
    {
        $listing = CarListing::factory()->for(User::factory()->suspended(), 'owner')->create();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->patch(route('admin.listings.approve', $listing), ['version' => $listing->moderationVersion()])
            ->assertSessionHas('success', __('app.listings.moderation.approved'));

        $this->assertSame(ListingStatus::Approved, $listing->fresh()->status);
        $this->actingAs($admin)->get(route('listings.show', $listing))
            ->assertSee(__('app.listings.show.status.owner_suspended'));
        $this->app['auth']->forgetGuards();
        $this->get(route('listings.show', $listing))->assertNotFound();
    }

    #[DataProvider('moderationRoutes')]
    public function test_a_transition_sends_no_notification_and_dispatches_no_event(string $route): void
    {
        $listing = CarListing::factory()->create();
        $admin = User::factory()->admin()->create();
        Notification::fake();
        Event::fake();

        $this->actingAs($admin)
            ->patch(route($route, $listing), $this->validPayload($route, $listing))
            ->assertSessionHas('success');

        Notification::assertNothingSent();
        $applicationEvents = array_filter(
            array_keys(Event::dispatchedEvents()),
            fn (string $event): bool => Str::startsWith($event, 'App\\'),
        );
        $this->assertSame([], array_values($applicationEvents));
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(string $route, CarListing $listing): array
    {
        return $route === 'admin.listings.reject'
            ? ['version' => $listing->moderationVersion(), 'rejection_reason' => 'The photos are too dark.']
            : ['version' => $listing->moderationVersion()];
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(CarListing $listing): array
    {
        return $listing->fresh()->getAttributes();
    }
}
