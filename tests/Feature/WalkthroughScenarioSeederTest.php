<?php

namespace Tests\Feature;

use App\Enums\ListingStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Events\MessageSent;
use App\Models\CarListing;
use App\Models\Conversation;
use App\Models\ListingImage;
use App\Models\MessageReport;
use App\Models\User;
use App\Services\SuspensionService;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;
use Database\Seeders\WalkthroughScenarioSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class WalkthroughScenarioSeederTest extends TestCase
{
    use LazilyRefreshDatabase;

    /** @var list<string> */
    private const array TABLES = ['users', 'car_listings', 'listing_images', 'conversations', 'messages'];

    /** @var list<string> */
    private const array EMAILS = [
        WalkthroughScenarioSeeder::SELLER_EMAIL,
        WalkthroughScenarioSeeder::ABUSER_EMAIL,
        WalkthroughScenarioSeeder::EXPIRED_OWNER_EMAIL,
        WalkthroughScenarioSeeder::BUYER_EMAIL,
    ];

    protected function setUp(): void
    {
        parent::setUp();

        config(['auth.admin.email' => 'boss@example.com', 'auth.admin.password' => 'initial1']);
        Storage::fake('public');
    }

    public function test_it_seeds_the_walkthrough_accounts_on_top_of_the_review_and_demo_data(): void
    {
        $this->freezeSecond();
        $this->seed(DatabaseSeeder::class);
        $admins = User::query()->where('role', UserRole::Admin)->count();
        Event::fake([MessageSent::class]);

        $this->artisan('db:seed', ['--class' => WalkthroughScenarioSeeder::class])
            ->expectsOutputToContain(WalkthroughScenarioSeeder::BUYER_EMAIL)
            ->expectsOutputToContain('Seller ↔ abuser thread: '.url('/conversations/'))
            ->assertSuccessful();

        Event::assertNotDispatched(MessageSent::class);
        $this->assertTrue(User::query()->where('email', 'review@example.com')->exists());
        $this->assertTrue(User::query()->where('email', DemoSeeder::MARKER_EMAIL)->exists());
        $this->assertSame($admins, User::query()->where('role', UserRole::Admin)->count());
        $this->assertFalse(User::query()->where('email', WalkthroughScenarioSeeder::BUYER_EMAIL)->exists());

        $seller = User::query()->where('email', WalkthroughScenarioSeeder::SELLER_EMAIL)->sole();
        $abuser = User::query()->where('email', WalkthroughScenarioSeeder::ABUSER_EMAIL)->sole();
        $expiredOwner = User::query()->where('email', WalkthroughScenarioSeeder::EXPIRED_OWNER_EMAIL)->sole();

        $this->assertSame([UserRole::AppUser, UserStatus::Active], [$seller->role, $seller->status]);
        $this->assertSame([UserRole::AppUser, UserStatus::Active], [$abuser->role, $abuser->status]);
        $this->assertSame([UserRole::AppUser, UserStatus::Suspended], [$expiredOwner->role, $expiredOwner->status]);
        $this->assertSame(now()->subHour()->toDateTimeString(), $expiredOwner->suspended_until->toDateTimeString());
        $this->assertSame(now()->subHour()->subDays(7)->toDateTimeString(), $expiredOwner->suspended_at->toDateTimeString());
        $this->assertNotEmpty($expiredOwner->suspension_reason);

        $this->assertFalse($seller->listings()->exists());
        $abuserListings = $abuser->listings()->get();
        $this->assertSame([ListingStatus::Approved, ListingStatus::Approved], $abuserListings->pluck('status')->all());
        $this->assertSame(2, $abuser->listings()->publiclyVisible()->count());

        $expiredListings = $expiredOwner->listings()->orderBy('id')->get();
        $this->assertSame([ListingStatus::Approved, ListingStatus::Approved, ListingStatus::Rejected], $expiredListings->pluck('status')->all());
        $this->assertNotEmpty($expiredListings[2]->rejection_reason);
        $this->assertFalse($expiredOwner->listings()->publiclyVisible()->exists());

        $walkthroughListings = $abuserListings->concat($expiredListings);
        $this->assertNotContains('Bel Air', $walkthroughListings->pluck('model')->all());
        $this->assertTrue($walkthroughListings->every(fn (CarListing $listing): bool => $listing->car_model_id !== null));

        $images = ListingImage::query()->whereIn('car_listing_id', $walkthroughListings->modelKeys())->get();
        $this->assertEqualsCanonicalizing($walkthroughListings->modelKeys(), $images->pluck('car_listing_id')->all());
        foreach ($images as $image) {
            foreach ([$image->path, $image->card_path, $image->thumb_path] as $path) {
                Storage::disk('public')->assertExists($path);
            }
        }

        $conversation = Conversation::query()->where('sender_id', $seller->id)->sole();
        $this->assertSame($abuser->id, $conversation->receiver_id);
        $this->assertContains($conversation->listing_id, $abuserListings->modelKeys());
        $messages = $conversation->messages()->orderBy('id')->get();
        $this->assertSame([$seller->id, $abuser->id], $messages->pluck('sender_id')->all());
        foreach ($messages as $message) {
            $this->assertNotNull($message->read_at);
            $this->assertGreaterThanOrEqual(5, mb_strlen($message->message));
            $this->assertLessThanOrEqual(100, mb_strlen($message->message));
        }
        $this->assertFalse(MessageReport::query()->whereIn('message_id', $messages->modelKeys())->exists());

        $this->assertSame([WalkthroughScenarioSeeder::EXPIRED_OWNER_EMAIL], User::query()->suspensionExpired()->pluck('email')->all());
        $this->assertSame(1, app(SuspensionService::class)->liftExpired());
        $this->assertSame(
            [$expiredListings[0]->id, $expiredListings[1]->id],
            $expiredOwner->listings()->publiclyVisible()->orderBy('car_listings.id')->pluck('car_listings.id')->all(),
        );
    }

    #[DataProvider('refusedEnvironments')]
    public function test_outside_local_and_testing_it_refuses_and_writes_nothing(string $environment): void
    {
        $this->seed(DatabaseSeeder::class);
        $before = $this->snapshot();

        $this->app['env'] = $environment;

        $this->artisan('db:seed', ['--class' => WalkthroughScenarioSeeder::class, '--force' => true])
            ->expectsOutputToContain('WalkthroughScenarioSeeder runs only in the local or testing environment.')
            ->assertSuccessful();

        $this->assertSame($before, $this->snapshot());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function refusedEnvironments(): array
    {
        return ['production' => ['production'], 'staging' => ['staging']];
    }

    #[DataProvider('walkthroughEmails')]
    public function test_an_existing_walkthrough_account_makes_it_refuse_before_any_write(string $email): void
    {
        $this->seed(DatabaseSeeder::class);
        User::factory()->create(['email' => $email]);
        $before = $this->snapshot();

        $this->artisan('db:seed', ['--class' => WalkthroughScenarioSeeder::class])
            ->expectsOutputToContain('Walkthrough accounts already exist.')
            ->assertSuccessful();

        $this->assertSame($before, $this->snapshot());
        $this->assertFalse(User::query()->where('email', 'review@example.com')->exists());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function walkthroughEmails(): array
    {
        return array_combine(self::EMAILS, array_map(fn (string $email): array => [$email], self::EMAILS));
    }

    public function test_the_database_seeder_alone_creates_no_walkthrough_row(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertFalse(User::query()->whereIn('email', self::EMAILS)->exists());
    }

    /**
     * @return array<string, int>
     */
    private function snapshot(): array
    {
        $counts = [];

        foreach (self::TABLES as $table) {
            $counts[$table] = DB::table($table)->count();
        }

        $counts['files'] = count(Storage::disk('public')->allFiles());

        return $counts;
    }
}
