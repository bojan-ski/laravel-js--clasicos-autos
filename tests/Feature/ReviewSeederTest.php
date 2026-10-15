<?php

namespace Tests\Feature;

use App\Enums\ListingStatus;
use App\Enums\ReportOutcome;
use App\Enums\ReportReason;
use App\Enums\ReportStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\CarListing;
use App\Models\Conversation;
use App\Models\ListingImage;
use App\Models\Message;
use App\Models\MessageReport;
use App\Models\User;
use Database\Seeders\CarMakerSeeder;
use Database\Seeders\CarModelSeeder;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\ReviewSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use LogicException;
use Tests\TestCase;

class ReviewSeederTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['auth.admin.email' => 'boss@example.com', 'auth.admin.password' => 'initial1']);
        Storage::fake('public');
    }

    public function test_the_reviewers_approved_pending_and_rejected_listings_carry_8_2_and_1_images_in_order(): void
    {
        $this->seed([DatabaseSeeder::class, ReviewSeeder::class]);

        $reviewer = User::query()->where('email', 'review@example.com')->sole();
        $counts = $reviewer->listings()->whereHas('images')->withCount('images')->get()
            ->mapWithKeys(fn (CarListing $listing): array => [$listing->status->value => $listing->images_count])
            ->sortKeys()
            ->all();

        $this->assertSame(['approved' => 8, 'pending' => 2, 'rejected' => 1], $counts);
        $this->assertSame(12, ListingImage::query()->count());

        foreach ($reviewer->listings()->whereHas('images')->with('images')->get() as $listing) {
            $this->assertSame(range(0, $listing->images->count() - 1), $listing->images->pluck('position')->sort()->values()->all());
        }
    }

    public function test_every_seeded_image_has_its_three_webp_files_on_the_public_disk(): void
    {
        $this->seed([DatabaseSeeder::class, ReviewSeeder::class]);

        $images = ListingImage::query()->get();

        $this->assertCount(12, $images);
        $this->assertCount(36, Storage::disk('public')->allFiles('listings'));

        foreach ($images as $image) {
            foreach ([$image->path, $image->card_path, $image->thumb_path] as $path) {
                $this->assertStringEndsWith('.webp', $path);
                Storage::disk('public')->assertExists($path);
            }
        }
    }

    public function test_seeding_images_leaves_the_pending_and_rejected_listings_as_seeded(): void
    {
        $this->seed([DatabaseSeeder::class, ReviewSeeder::class]);

        $reviewer = User::query()->where('email', 'review@example.com')->sole();
        $pending = $reviewer->listings()->where('status', ListingStatus::Pending)->sole();
        $rejected = $reviewer->listings()->where('status', ListingStatus::Rejected)->sole();

        $this->assertNull($pending->approved_at);
        $this->assertNull($pending->rejected_at);
        $this->assertSame('The photos do not show the car clearly.', $rejected->rejection_reason);
        $this->assertNotNull($rejected->rejected_at);
    }

    public function test_it_seeds_two_pages_of_public_listings_across_several_seeded_makers(): void
    {
        $this->seed([DatabaseSeeder::class, ReviewSeeder::class]);

        $public = CarListing::query()->publiclyVisible()->get();

        $this->assertGreaterThanOrEqual(13, $public->count());
        $this->assertGreaterThanOrEqual(4, $public->pluck('car_maker_id')->unique()->count());
        $this->assertFalse($public->contains(fn (CarListing $listing): bool => $listing->car_model_id === null));
        $this->assertSame(1, $public->filter(fn (CarListing $listing): bool => $listing->images()->exists())->count());
    }

    public function test_the_review_user_owns_a_pending_and_a_rejected_listing_with_a_reason(): void
    {
        $this->seed([DatabaseSeeder::class, ReviewSeeder::class]);

        $reviewer = User::query()->where('email', 'review@example.com')->sole();

        $this->assertSame(1, $reviewer->listings()->where('status', ListingStatus::Pending)->count());
        $rejected = $reviewer->listings()->where('status', ListingStatus::Rejected)->sole();
        $this->assertNotEmpty($rejected->rejection_reason);
    }

    public function test_a_suspended_owner_has_one_approved_listing_that_is_not_public(): void
    {
        $this->freezeTime();
        $this->seed([DatabaseSeeder::class, ReviewSeeder::class]);

        $owner = User::query()->where('status', UserStatus::Suspended)->sole();

        $this->assertSame(now()->addDays(30)->toDateTimeString(), $owner->suspended_until->toDateTimeString());
        $listing = $owner->listings()->sole();
        $this->assertSame(ListingStatus::Approved, $listing->status);
        $this->assertFalse(CarListing::query()->publiclyVisible()->whereKey($listing->id)->exists());
    }

    public function test_approved_listings_are_moderated_by_the_seeded_admin(): void
    {
        $this->seed([DatabaseSeeder::class, ReviewSeeder::class]);

        $admin = User::query()->where('role', UserRole::Admin)->sole();

        $this->assertSame([$admin->id], CarListing::query()->where('status', ListingStatus::Approved)->distinct()->pluck('moderated_by')->all());
    }

    public function test_the_review_user_is_an_active_app_user_who_can_sign_in(): void
    {
        $this->seed([DatabaseSeeder::class, ReviewSeeder::class]);

        $this->post(route('login.store'), ['email' => 'review@example.com', 'password' => 'password'])
            ->assertRedirect(route('profile'));
        $this->assertAuthenticatedAs(User::query()->where('email', 'review@example.com')->sole());
    }

    public function test_the_stranger_is_an_active_app_user_with_a_long_name_and_two_pages_of_listings(): void
    {
        $this->seed([DatabaseSeeder::class, ReviewSeeder::class]);

        $stranger = User::query()->where('email', 'stranger@example.com')->sole();

        $this->assertGreaterThanOrEqual(48, mb_strlen($stranger->username));
        $this->assertLessThanOrEqual(64, mb_strlen($stranger->username));
        $this->assertGreaterThanOrEqual(13, $stranger->listings()->publiclyVisible()->count());
        $this->post(route('login.store'), ['email' => 'stranger@example.com', 'password' => 'password'])
            ->assertRedirect(route('profile'));
        $this->assertAuthenticatedAs($stranger);
    }

    public function test_the_review_user_bookmarks_two_pages_of_public_listings_and_the_suspended_owners_listing(): void
    {
        $this->seed([DatabaseSeeder::class, ReviewSeeder::class]);

        $reviewer = User::query()->where('email', 'review@example.com')->sole();
        $suspendedListing = User::query()->where('status', UserStatus::Suspended)->sole()->listings()->sole();

        $this->assertGreaterThanOrEqual(13, $reviewer->bookmarks()->publiclyVisible()->count());
        $this->assertTrue($reviewer->bookmarks()->whereKey($suspendedListing->id)->exists());
        $this->assertFalse($reviewer->bookmarks()->where('car_listings.user_id', $reviewer->id)->exists());
    }

    public function test_the_review_user_has_a_conversation_with_the_stranger_about_one_of_their_listings(): void
    {
        $this->seed([DatabaseSeeder::class, ReviewSeeder::class]);

        $reviewer = User::query()->where('email', 'review@example.com')->sole();
        $stranger = User::query()->where('email', 'stranger@example.com')->sole();
        $conversation = Conversation::query()->where('receiver_id', $stranger->id)->with(['listing', 'messages'])->sole();

        $this->assertSame([$reviewer->id, $stranger->id, $stranger->id], [$conversation->sender_id, $conversation->receiver_id, $conversation->listing->user_id]);
        $this->assertTrue($conversation->listing->isApproved());
        $this->assertSame([$reviewer->id, $stranger->id, $reviewer->id, $stranger->id], $conversation->messages->pluck('sender_id')->all());
        $this->assertContains(100, $conversation->messages->map(fn (Message $message): int => mb_strlen($message->message))->all());
        $this->assertTrue($conversation->last_message_at->equalTo($conversation->messages->last()->created_at));
    }

    public function test_only_the_newest_message_from_the_stranger_is_unread(): void
    {
        $this->seed([DatabaseSeeder::class, ReviewSeeder::class]);

        $messages = Conversation::query()->whereRelation('receiver', 'email', 'stranger@example.com')->sole()->messages()->get();

        $this->assertSame([false, false, false, true], $messages->map(fn (Message $message): bool => $message->read_at === null)->all());
    }

    public function test_the_review_user_sells_in_an_older_read_conversation_about_the_photographed_listing(): void
    {
        $this->seed([DatabaseSeeder::class, ReviewSeeder::class]);

        $reviewer = User::query()->where('email', 'review@example.com')->sole();
        $buyer = User::query()->where('email', 'buyer@example.com')->sole();
        $sold = Conversation::query()->where('receiver_id', $reviewer->id)->with(['listing.images', 'messages'])->sole();
        $bought = Conversation::query()->where('sender_id', $reviewer->id)->sole();

        $this->assertTrue($buyer->isActiveAppUser());
        $this->assertSame([$buyer->id, $reviewer->id], [$sold->sender_id, $sold->listing->user_id]);
        $this->assertTrue($sold->listing->isApproved());
        $this->assertCount(8, $sold->listing->images);
        $this->assertSame([$buyer->id, $reviewer->id, $buyer->id], $sold->messages->pluck('sender_id')->all());
        $this->assertSame([], $sold->messages->whereNull('read_at')->all());
        $this->assertTrue($sold->last_message_at->lessThan($bought->last_message_at));
        $this->assertSame(2, Conversation::query()->involving($reviewer)->count());
    }

    public function test_the_stranger_owns_the_oldest_pending_custom_model_listing_with_one_photo(): void
    {
        $this->freezeTime();
        $this->seed([DatabaseSeeder::class, ReviewSeeder::class]);

        $stranger = User::query()->where('email', 'stranger@example.com')->sole();
        $roadster = CarListing::query()->where('name', 'Custom Roadster')->withCount('images')->sole();
        $conversation = Conversation::query()->where('receiver_id', $stranger->id)->sole();

        $this->assertSame($stranger->id, $roadster->user_id);
        $this->assertTrue($roadster->canPromoteModel());
        $this->assertSame('Roadster', $roadster->model);
        $this->assertSame(1, $roadster->images_count);
        $this->assertSame(now()->subDays(2)->toDateTimeString(), $roadster->submitted_at->toDateTimeString());
        $this->assertSame($roadster->id, CarListing::query()->where('status', ListingStatus::Pending)->orderBy('submitted_at')->orderBy('id')->value('id'));
        $this->assertNotSame($roadster->id, $conversation->listing_id);
    }

    public function test_each_seeded_conversation_spans_exactly_its_messages(): void
    {
        $this->seed([DatabaseSeeder::class, ReviewSeeder::class]);

        foreach (Conversation::query()->with('messages')->get() as $conversation) {
            $this->assertTrue($conversation->created_at->equalTo($conversation->messages->first()->created_at));
            $this->assertTrue($conversation->last_message_at->equalTo($conversation->messages->last()->created_at));
        }
    }

    public function test_the_reviewer_has_reported_the_strangers_last_message_and_the_buyers_report_was_dismissed(): void
    {
        $this->seed([DatabaseSeeder::class, ReviewSeeder::class]);
        $reviewer = User::query()->where('email', 'review@example.com')->sole();
        $buyer = User::query()->where('email', 'buyer@example.com')->sole();
        $admin = User::query()->where('role', UserRole::Admin)->sole();

        $open = MessageReport::query()->open()->with('message')->sole();
        $strangersLast = Conversation::query()->where('sender_id', $reviewer->id)->sole()->messages()->get()->last();
        $this->assertSame([$reviewer->id, $strangersLast->id, $strangersLast->message, ReportReason::Other], [$open->reporter_id, $open->message_id, $open->message_text, $open->reason]);
        $this->assertNotNull($open->note);

        $dismissed = MessageReport::query()->where('status', ReportStatus::Resolved)->sole();
        $reviewerReply = Conversation::query()->where('receiver_id', $reviewer->id)->sole()->messages()->get()[1];
        $this->assertSame([$buyer->id, $reviewerReply->id, $reviewer->id, ReportReason::Scam, ReportOutcome::Dismissed, $admin->id], [
            $dismissed->reporter_id, $dismissed->message_id, $dismissed->reported_user_id, $dismissed->reason, $dismissed->outcome, $dismissed->resolved_by,
        ]);
        $this->assertSame(1, User::query()->where('role', UserRole::Admin)->count());
    }

    public function test_without_an_admin_the_moderated_listings_have_no_moderator(): void
    {
        $this->seed([CarMakerSeeder::class, CarModelSeeder::class, ReviewSeeder::class]);

        $this->assertSame(0, User::query()->where('role', UserRole::Admin)->count());
        $this->assertSame([null], CarListing::query()->distinct()->pluck('moderated_by')->all());
        $this->assertSame([null], MessageReport::query()->distinct()->pluck('resolved_by')->all());
    }

    public function test_a_second_run_adds_nothing(): void
    {
        $this->seed([DatabaseSeeder::class, ReviewSeeder::class]);
        $users = User::query()->count();
        $listings = CarListing::query()->count();
        $bookmarks = DB::table('user_bookmarks')->count();
        $images = ListingImage::query()->count();
        $files = Storage::disk('public')->allFiles();
        $messages = Message::query()->count();

        $this->seed(ReviewSeeder::class);

        $this->assertSame(2, Conversation::query()->count());
        $this->assertSame(2, MessageReport::query()->count());
        $this->assertSame($messages, Message::query()->count());
        $this->assertSame($users, User::query()->count());
        $this->assertSame($listings, CarListing::query()->count());
        $this->assertSame($bookmarks, DB::table('user_bookmarks')->count());
        $this->assertSame($images, ListingImage::query()->count());
        $this->assertSame($files, Storage::disk('public')->allFiles());
    }

    public function test_it_refuses_to_run_without_the_seeded_makers_and_models(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('migrate:fresh --seed');

        $this->seed(ReviewSeeder::class);
    }
}
