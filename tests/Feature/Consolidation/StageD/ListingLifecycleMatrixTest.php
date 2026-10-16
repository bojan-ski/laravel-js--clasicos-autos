<?php

namespace Tests\Feature\Consolidation\StageD;

use App\Enums\DocumentationStatus;
use App\Enums\EngineCondition;
use App\Enums\EngineHistory;
use App\Enums\FuelType;
use App\Enums\ListingStatus;
use App\Enums\Odometer;
use App\Enums\RestorationHistory;
use App\Enums\Transmission;
use App\Models\CarListing;
use App\Models\CarMaker;
use App\Models\CarModel;
use App\Models\Conversation;
use App\Models\ListingImage;
use App\Models\Message;
use App\Models\MessageReport;
use App\Models\User;
use App\Services\ListingImageService;
use App\Support\ListingFormValues;
use App\Support\SuspensionText;
use Generator;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ListingLifecycleMatrixTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const string OLD_REASON = 'The first review found blurry photos.';

    private const string REASON = 'The engine bay photo is missing.';

    private const string PROMOTED_MODEL = 'Nomad';

    private const array OWNER_OR_ADMIN_ACTIONS = ['edit', 'add_image', 'delete_image', 'set_primary', 'set_primary_on_primary', 'delete'];

    private const array MODERATION_ACTIONS = ['approve', 'reject'];

    private User $admin;

    private User $previousModerator;

    private CarModel $belAir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Storage::fake('public');
        $this->freezeTime();

        $this->admin = User::factory()->admin()->create();
        $this->previousModerator = User::factory()->admin()->create();
        $maker = CarMaker::factory()->create(['name' => 'Chevrolet']);
        $this->belAir = CarModel::factory()->for($maker, 'maker')->create(['name' => 'Bel Air', 'year_from' => 1950, 'year_to' => 1981]);
    }

    /**
     * @return Generator<string, array{string, ListingStatus, string, string}>
     */
    public static function ownerOrAdminWrites(): Generator
    {
        $cells = [
            'edit' => ['pending' => 'K', 'approved' => 'R', 'rejected' => 'R'],
            'add_image' => ['pending' => 'K', 'approved' => 'R', 'rejected' => 'R'],
            'delete_image' => ['pending' => 'K', 'approved' => 'R', 'rejected' => 'R'],
            'set_primary' => ['pending' => 'K', 'approved' => 'R', 'rejected' => 'R'],
            'set_primary_on_primary' => ['pending' => 'K', 'approved' => 'K', 'rejected' => 'K'],
        ];

        foreach ($cells as $action => $byStatus) {
            foreach ($byStatus as $status => $cell) {
                foreach (['owner', 'admin'] as $actor) {
                    yield "{$action} from {$status} by {$actor}" => [$action, ListingStatus::from($status), $actor, $cell];
                }
            }
        }
    }

    #[DataProvider('ownerOrAdminWrites')]
    public function test_an_owner_or_admin_write_lands_in_its_cell(string $action, ListingStatus $from, string $actor, string $cell): void
    {
        $owner = User::factory()->create();
        $listing = $this->listingIn($from, $owner);
        $before = $this->columns($listing);
        $files = $this->files($listing);
        $first = $this->imageAt($listing, 0);
        $second = $this->imageAt($listing, 1);

        $response = $this->perform($action, $listing, $actor === 'admin' ? $this->admin : $owner);

        $response->assertRedirect(route($action === 'edit' ? 'listings.show' : 'listings.images.edit', $listing))
            ->assertSessionHas('success', __(match ($action) {
                'edit' => 'app.listings.edit.updated',
                'add_image' => 'app.listings.images.gallery_updated',
                'delete_image' => 'app.listings.images.deleted',
                default => 'app.listings.images.primary_changed',
            }));
        $this->assertSame($cell === 'R' ? $this->returnedToPending($before) : $before, $this->columns($listing));

        $images = $listing->images()->orderBy('position')->get();
        $imageCount = ['add_image' => 3, 'delete_image' => 1][$action] ?? 2;
        $this->assertCount(6, $files);
        $this->assertSame(range(0, $imageCount - 1), $images->pluck('position')->all());
        $this->assertCount($imageCount * 3, $this->files($listing));

        if ($action === 'delete_image') {
            $this->assertSame([], array_intersect([$second->path, $second->card_path, $second->thumb_path], $this->files($listing)));
        }

        if (in_array($action, ['edit', 'set_primary', 'set_primary_on_primary'], true)) {
            $this->assertSame($files, $this->files($listing));
            $this->assertSame($action === 'set_primary' ? [$second->id, $first->id] : [$first->id, $second->id], $images->pluck('id')->all());
        }
    }

    /**
     * @return Generator<string, array{string, ListingStatus, string}>
     */
    public static function moderationWrites(): Generator
    {
        $cells = [
            'approve' => ['pending' => 'A', 'approved' => 'G', 'rejected' => 'G'],
            'reject' => ['pending' => 'J', 'approved' => 'G', 'rejected' => 'G'],
            'approve_stale' => ['pending' => 'S', 'approved' => 'G', 'rejected' => 'G'],
            'reject_stale' => ['pending' => 'S', 'approved' => 'G', 'rejected' => 'G'],
        ];

        foreach ($cells as $action => $byStatus) {
            foreach ($byStatus as $status => $cell) {
                yield "{$action} from {$status}" => [$action, ListingStatus::from($status), $cell];
            }
        }
    }

    #[DataProvider('moderationWrites')]
    public function test_an_admin_moderation_lands_in_its_cell(string $action, ListingStatus $from, string $cell): void
    {
        $listing = $this->listingIn($from, User::factory()->create());
        $before = $this->columns($listing);
        $files = $this->files($listing);

        $response = $this->perform($action, $listing, $this->admin)
            ->assertRedirect(route('listings.show', $listing));

        $this->assertModerationCell($response, $cell, $listing, $before, $files);
    }

    #[DataProvider('moderationWrites')]
    public function test_an_admin_moderation_from_the_queue_lands_in_its_cell_and_returns_to_the_queue(string $action, ListingStatus $from, string $cell): void
    {
        $listing = $this->listingIn($from, User::factory()->create());
        $before = $this->columns($listing);
        $files = $this->files($listing);

        $response = $this->perform($action, $listing, $this->admin, ['return' => 'queue'])
            ->assertRedirect(route('admin.listings'));

        $this->assertModerationCell($response, $cell, $listing, $before, $files);
    }

    /**
     * @return array<string, array{ListingStatus, bool, string}>
     */
    public static function promotions(): array
    {
        return [
            'promote a pending custom model' => [ListingStatus::Pending, true, 'P'],
            'promote a pending listed model' => [ListingStatus::Pending, false, 'G'],
            'promote an approved custom model' => [ListingStatus::Approved, true, 'G'],
            'promote a rejected custom model' => [ListingStatus::Rejected, true, 'G'],
        ];
    }

    #[DataProvider('promotions')]
    public function test_a_promotion_links_the_model_without_a_transition(ListingStatus $from, bool $customModel, string $cell): void
    {
        $listing = $this->listingIn($from, User::factory()->create(), $customModel);
        $before = $this->columns($listing);
        $beforeModel = $this->modelColumns($listing);
        $models = CarModel::query()->count();

        $response = $this->perform('promote', $listing, $this->admin)->assertRedirect(route('admin.listings'));

        $this->assertSame($before, $this->columns($listing));

        if ($cell === 'G') {
            $response->assertSessionHas('error', __('app.admin.listings.promote.not_promotable'));
            $this->assertSame($beforeModel, $this->modelColumns($listing));
            $this->assertSame($models, CarModel::query()->count());

            return;
        }

        $promoted = CarModel::query()->where('name', self::PROMOTED_MODEL)->sole();
        $response->assertSessionHas('success', __('app.admin.listings.promote.flash.created', ['model' => self::PROMOTED_MODEL]));
        $this->assertSame(['car_model_id' => $promoted->id, 'model' => self::PROMOTED_MODEL, 'updated_at' => now()->toDateTimeString()], $this->modelColumns($listing));
    }

    /**
     * @param  array{status: string, submitted_at: ?string, approved_at: ?string, rejected_at: ?string, rejection_reason: ?string, moderated_by: ?int}  $before
     * @param  list<string>  $files
     */
    private function assertModerationCell(TestResponse $response, string $cell, CarListing $listing, array $before, array $files): void
    {
        $now = now()->toDateTimeString();
        $expected = match ($cell) {
            'A' => [...$before, 'status' => 'approved', 'approved_at' => $now, 'rejected_at' => null, 'rejection_reason' => null, 'moderated_by' => $this->admin->id],
            'J' => [...$before, 'status' => 'rejected', 'approved_at' => null, 'rejected_at' => $now, 'rejection_reason' => self::REASON, 'moderated_by' => $this->admin->id],
            default => $before,
        };
        $this->assertSame($expected, $this->columns($listing));
        $this->assertSame($files, $this->files($listing));

        match ($cell) {
            'A' => $response->assertSessionHas('success', __('app.listings.moderation.approved')),
            'J' => $response->assertSessionHas('success', __('app.listings.moderation.rejected')),
            'S' => $response->assertSessionHas('error', __('app.listings.moderation.changed')),
            'G' => $response->assertSessionHas('error', __('app.listings.moderation.not_pending')),
        };
    }

    /**
     * @return Generator<string, array{ListingStatus, string}>
     */
    public static function deletions(): Generator
    {
        foreach (ListingStatus::cases() as $status) {
            foreach (['owner', 'admin'] as $actor) {
                yield "delete from {$status->value} by {$actor}" => [$status, $actor];
            }
        }
    }

    #[DataProvider('deletions')]
    public function test_deleting_a_listing_removes_its_row_images_bookmarks_and_files(ListingStatus $from, string $actor): void
    {
        $owner = User::factory()->create();
        $listing = $this->listingIn($from, $owner);
        $other = $this->listingIn(ListingStatus::Approved, User::factory()->create());
        $otherFiles = $this->files($other);
        User::factory()->create()->bookmarks()->attach([$listing->id, $other->id]);
        [$conversation, $report] = $this->conversationOn($listing);
        [$otherConversation] = $this->conversationOn($other);
        $messageIds = $conversation->messages()->pluck('id')->all();
        $this->assertCount(2, $messageIds);
        $this->assertModelExists($report);

        $this->perform('delete', $listing, $actor === 'admin' ? $this->admin : $owner)
            ->assertRedirect(route($actor === 'admin' ? 'admin.listings' : 'profile'))
            ->assertSessionHas('success', __('app.listings.edit.deleted'));

        $this->assertModelMissing($listing);
        $this->assertSame(0, ListingImage::query()->where('car_listing_id', $listing->id)->count());
        $this->assertSame(0, DB::table('user_bookmarks')->where('car_listing_id', $listing->id)->count());
        $this->assertFalse(Storage::disk('public')->directoryExists("listings/{$listing->id}"));
        $this->assertSame(1, DB::table('user_bookmarks')->where('car_listing_id', $other->id)->count());
        $this->assertSame($otherFiles, $this->files($other));
        $this->assertModelMissing($conversation);
        $this->assertSame(0, Message::query()->whereIn('id', $messageIds)->count());
        $this->assertModelMissing($report);
        $this->assertModelExists($otherConversation);
        $this->assertSame(2, $otherConversation->messages()->count());
    }

    public function test_create_is_the_entry_edge_pending_with_submitted_at_now_and_two_images(): void
    {
        $owner = User::factory()->create();

        $this->actingAs($owner)->post(route('listings.store'), $this->createPayload())->assertRedirect();

        $listing = CarListing::query()->sole();
        $this->assertSame([
            'status' => 'pending',
            'submitted_at' => now()->toDateTimeString(),
            'approved_at' => null,
            'rejected_at' => null,
            'rejection_reason' => null,
            'moderated_by' => null,
        ], $this->columns($listing));
        $this->assertSame(2, $listing->images()->count());
        $this->assertCount(6, $this->files($listing));
    }

    /**
     * @return Generator<string, array{string, ListingStatus, string, string}>
     */
    public static function refusals(): Generator
    {
        foreach (ListingStatus::cases() as $status) {
            foreach ([...self::OWNER_OR_ADMIN_ACTIONS, ...self::MODERATION_ACTIONS] as $action) {
                yield "{$action} on {$status->value} by a guest" => [$action, $status, 'guest', 'login'];

                $strangerOutcome = in_array($action, self::MODERATION_ACTIONS, true) || $status === ListingStatus::Approved ? '403' : '404';
                yield "{$action} on {$status->value} by a stranger" => [$action, $status, 'stranger', $strangerOutcome];
                yield "{$action} on {$status->value} by the suspended owner in a live session" => [$action, $status, 'suspended owner', 'signed out'];
            }

            foreach (self::MODERATION_ACTIONS as $action) {
                yield "{$action} on {$status->value} by the owner" => [$action, $status, 'owner', '403'];
            }
        }
    }

    #[DataProvider('refusals')]
    public function test_a_refused_actor_changes_no_column_row_or_file(string $action, ListingStatus $from, string $actor, string $outcome): void
    {
        $owner = $actor === 'suspended owner' ? User::factory()->suspended()->create() : User::factory()->create();
        $listing = $this->listingIn($from, $owner);
        $before = $this->columns($listing);
        $images = $listing->images()->orderBy('position')->pluck('id')->all();
        $files = $this->files($listing);

        $response = $this->perform($action, $listing, match ($actor) {
            'guest' => null,
            'stranger' => User::factory()->create(),
            default => $owner,
        });

        match ($outcome) {
            'login', 'signed out' => $response->assertRedirect(route('login')),
            '403' => $response->assertForbidden()->assertSee(__('app.errors.403.title')),
            '404' => $response->assertNotFound()->assertSee(__('app.errors.404.title')),
        };
        $this->assertSame($before, $this->columns($listing));
        $this->assertSame($images, $listing->images()->orderBy('position')->pluck('id')->all());
        $this->assertSame($files, $this->files($listing));

        if ($outcome === 'signed out') {
            $response->assertSessionHasErrors(['email' => SuspensionText::notice($owner)]);
            $this->assertGuest();
        }
    }

    /**
     * @param  array<string, string>  $query
     */
    private function perform(string $action, CarListing $listing, ?User $actor, array $query = []): TestResponse
    {
        if ($actor === null) {
            $this->app['auth']->forgetGuards();
        } else {
            $this->actingAs($actor);
        }

        $version = $listing->refresh()->moderationVersion();
        $moderated = [$listing, ...$query];

        return match ($action) {
            'edit' => $this->put(route('listings.update', $listing), ListingFormValues::for($listing)),
            'add_image' => $this->post(route('listings.images.store', $listing), ['images' => [UploadedFile::fake()->image('added.jpg', 40, 30)]]),
            'delete_image' => $this->delete(route('listings.images.destroy', [$listing, $this->imageAt($listing, 1)])),
            'set_primary' => $this->patch(route('listings.images.primary', [$listing, $this->imageAt($listing, 1)])),
            'set_primary_on_primary' => $this->patch(route('listings.images.primary', [$listing, $this->imageAt($listing, 0)])),
            'delete' => $this->delete(route('listings.destroy', $listing)),
            'approve' => $this->patch(route('admin.listings.approve', $moderated), ['version' => $version]),
            'reject' => $this->patch(route('admin.listings.reject', $moderated), ['version' => $version, 'rejection_reason' => '  '.self::REASON.'  ']),
            'approve_stale' => $this->patch(route('admin.listings.approve', $moderated), ['version' => $version - 1]),
            'reject_stale' => $this->patch(route('admin.listings.reject', $moderated), ['version' => $version - 1, 'rejection_reason' => self::REASON]),
            'promote' => $this->patch(route('admin.listings.promote', $moderated), ['name' => self::PROMOTED_MODEL, 'year_from' => 1955, 'year_to' => 1961, 'version' => $version]),
        };
    }

    private function listingIn(ListingStatus $status, User $owner, bool $customModel = false): CarListing
    {
        $factory = $customModel
            ? CarListing::factory()->customModel()->state(['car_maker_id' => $this->belAir->car_maker_id, 'model' => 'Bel Air Wagon'])
            : CarListing::factory()->forCarModel($this->belAir);

        $listing = $factory->for($owner, 'owner')->create([
            'year' => 1957,
            'status' => $status,
            'submitted_at' => now()->subDays(10),
            'approved_at' => $status === ListingStatus::Approved ? now()->subDays(5) : null,
            'rejected_at' => $status === ListingStatus::Rejected ? now()->subDays(5) : null,
            'rejection_reason' => $status === ListingStatus::Rejected ? self::OLD_REASON : null,
            'moderated_by' => $status === ListingStatus::Pending ? null : $this->previousModerator->id,
            'created_at' => now()->subDays(10),
            'updated_at' => now()->subDays(5),
        ]);

        DB::transaction(fn () => app(ListingImageService::class)->storeForNewListing($listing, [
            UploadedFile::fake()->image('front.jpg', 40, 30),
            UploadedFile::fake()->image('back.jpg', 40, 30),
        ]));

        return $listing;
    }

    /**
     * @return array{status: string, submitted_at: ?string, approved_at: ?string, rejected_at: ?string, rejection_reason: ?string, moderated_by: ?int}
     */
    private function columns(CarListing $listing): array
    {
        $row = DB::table('car_listings')->where('id', $listing->id)->sole();

        return [
            'status' => $row->status,
            'submitted_at' => $row->submitted_at,
            'approved_at' => $row->approved_at,
            'rejected_at' => $row->rejected_at,
            'rejection_reason' => $row->rejection_reason,
            'moderated_by' => $row->moderated_by,
        ];
    }

    /**
     * @param  array{status: string, submitted_at: ?string, approved_at: ?string, rejected_at: ?string, rejection_reason: ?string, moderated_by: ?int}  $before
     * @return array{status: string, submitted_at: ?string, approved_at: ?string, rejected_at: ?string, rejection_reason: ?string, moderated_by: ?int}
     */
    private function returnedToPending(array $before): array
    {
        return [
            ...$before,
            'status' => 'pending',
            'submitted_at' => now()->toDateTimeString(),
            'approved_at' => null,
            'rejected_at' => null,
            'rejection_reason' => null,
        ];
    }

    /**
     * @return array{car_model_id: ?int, model: string, updated_at: string}
     */
    private function modelColumns(CarListing $listing): array
    {
        return (array) DB::table('car_listings')->where('id', $listing->id)->first(['car_model_id', 'model', 'updated_at']);
    }

    /**
     * @return array{Conversation, MessageReport}
     */
    private function conversationOn(CarListing $listing): array
    {
        $conversation = Conversation::factory()->for($listing, 'listing')->create();
        Message::factory()->for($conversation)->create(['sender_id' => $conversation->sender_id]);
        $ownersMessage = Message::factory()->for($conversation)->create(['sender_id' => $conversation->receiver_id]);

        return [$conversation, MessageReport::factory()->create(['message_id' => $ownersMessage->id])];
    }

    private function imageAt(CarListing $listing, int $position): ListingImage
    {
        return $listing->images()->where('position', $position)->sole();
    }

    /**
     * @return list<string>
     */
    private function files(CarListing $listing): array
    {
        return Storage::disk('public')->allFiles("listings/{$listing->id}");
    }

    /**
     * @return array<string, mixed>
     */
    private function createPayload(): array
    {
        return [
            'name' => 'Matrix Bel Air',
            'car_maker_id' => $this->belAir->car_maker_id,
            'car_model_id' => $this->belAir->id,
            'price' => 25000,
            'year' => 1957,
            'mileage' => 120000,
            'transmission' => Transmission::Manual->value,
            'fuel_type' => FuelType::Gasoline->value,
            'engine_size' => '4.6L',
            'engine_type' => 'V8',
            'engine_history' => EngineHistory::cases()[0]->value,
            'engine_condition' => EngineCondition::cases()[0]->value,
            'restoration_history' => RestorationHistory::cases()[0]->value,
            'original_parts_percentage' => 80,
            'odometer' => Odometer::cases()[0]->value,
            'documentation_status' => DocumentationStatus::cases()[0]->value,
            'location_city' => 'Havana',
            'location_state' => 'La Habana',
            'location_zipcode' => '10400',
            'description' => 'Two-tone paint, original interior.',
            'images' => [UploadedFile::fake()->image('front.jpg', 40, 30), UploadedFile::fake()->image('back.jpg', 40, 30)],
        ];
    }
}
