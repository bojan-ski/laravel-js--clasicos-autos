<?php

namespace Tests\Feature;

use App\Enums\ListingStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Events\MessageSent;
use App\Models\CarListing;
use App\Models\ListingImage;
use App\Models\Message;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;
use Database\Seeders\ReviewSeeder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DemoSeederTest extends TestCase
{
    use LazilyRefreshDatabase;

    /** @var list<string> */
    private const array TABLES = ['users', 'car_listings', 'listing_images', 'user_bookmarks', 'conversations', 'messages'];

    protected function setUp(): void
    {
        parent::setUp();

        config(['auth.admin.email' => 'boss@example.com', 'auth.admin.password' => 'initial1']);
        Storage::fake('public');
    }

    public function test_outside_local_and_testing_it_warns_and_writes_nothing(): void
    {
        $this->seed(DatabaseSeeder::class);
        $before = $this->snapshot();

        $this->app['env'] = 'production';

        $this->artisan('db:seed', ['--class' => DemoSeeder::class, '--force' => true])
            ->expectsOutputToContain('DemoSeeder runs only in the local or testing environment.')
            ->assertSuccessful();

        $this->assertSame($before, $this->snapshot());
    }

    public function test_it_seeds_the_demo_marketplace_without_new_admins_or_broadcasts(): void
    {
        Event::fake([MessageSent::class]);
        $this->seed(DatabaseSeeder::class);
        $admins = User::query()->where('role', UserRole::Admin)->count();

        $this->seed(DemoSeeder::class);

        $this->assertSame($admins, User::query()->where('role', UserRole::Admin)->count());
        Event::assertNotDispatched(MessageSent::class);

        $demoUsers = $this->demoUsers()->get();
        $this->assertTrue($demoUsers->every(fn (User $user): bool => $user->isAppUser()));
        $this->assertGreaterThanOrEqual(25, $demoUsers->count());
        $this->assertLessThanOrEqual(35, $demoUsers->count());

        $listings = CarListing::query()->whereIn('user_id', $demoUsers->modelKeys())->withCount('images')->get();
        $this->assertGreaterThanOrEqual(30, $listings->count());
        $this->assertLessThanOrEqual(50, $listings->count());
        $this->assertEqualsCanonicalizing(
            array_column(ListingStatus::cases(), 'value'),
            $listings->pluck('status')->map->value->unique()->values()->all(),
        );
        $this->assertTrue($listings->every(fn (CarListing $listing): bool => $listing->images_count >= 1 && $listing->images_count <= 8));
        $this->assertSame(8, $listings->max('images_count'));

        foreach (ListingImage::query()->whereIn('car_listing_id', $listings->modelKeys())->get() as $image) {
            foreach ([$image->path, $image->card_path, $image->thumb_path] as $path) {
                Storage::disk('public')->assertExists($path);
            }
        }

        $suspended = $this->demoUsers()->where('status', UserStatus::Suspended)->sole();
        $this->assertTrue($suspended->suspended_until === null || $suspended->suspended_until->isFuture());
        $this->assertSame(0, $this->demoUsers()->suspensionExpired()->count());
        $this->assertTrue($suspended->listings()->exists());
        $this->assertFalse($suspended->listings()->publiclyVisible()->exists());

        $bookmarks = DB::table('user_bookmarks')->whereIn('user_id', $demoUsers->modelKeys())->get();
        $this->assertNotEmpty($bookmarks);

        foreach ($bookmarks as $bookmark) {
            $listing = CarListing::query()->publiclyVisible()->find($bookmark->car_listing_id);
            $this->assertNotNull($listing);
            $this->assertNotSame($bookmark->user_id, $listing->user_id);
        }

        $messages = Message::query()->get();
        $this->assertTrue($messages->contains(fn (Message $message): bool => $message->read_at === null));
        $this->assertTrue($messages->every(fn (Message $message): bool => mb_strlen($message->message) >= config('listings.message.min')
            && mb_strlen($message->message) <= config('listings.message.max')));
    }

    public function test_a_second_run_adds_nothing_and_review_seeder_still_runs_after_it(): void
    {
        $this->seed([DatabaseSeeder::class, DemoSeeder::class]);
        $before = $this->snapshot();

        $this->seed(DemoSeeder::class);

        $this->assertSame($before, $this->snapshot());

        $this->seed(ReviewSeeder::class);

        $this->assertTrue(User::query()->where('email', DemoSeeder::MARKER_EMAIL)->exists());
        $this->assertTrue(User::query()->where('email', 'review@example.com')->exists());
    }

    public function test_it_runs_after_review_seeder(): void
    {
        $this->seed([DatabaseSeeder::class, ReviewSeeder::class, DemoSeeder::class]);

        $this->assertTrue(User::query()->where('email', 'review@example.com')->exists());
        $this->assertTrue(User::query()->where('email', DemoSeeder::MARKER_EMAIL)->exists());
        $this->assertSame(30, $this->demoUsers()->count());
    }

    public function test_the_database_seeder_alone_creates_no_demo_row(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(0, $this->demoUsers()->count());
        $this->assertSame(0, CarListing::query()->count());
    }

    /**
     * @return Builder<User>
     */
    private function demoUsers(): Builder
    {
        return User::query()->where('email', 'like', '%@demo.test');
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
