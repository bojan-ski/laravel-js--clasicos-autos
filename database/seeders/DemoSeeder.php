<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\CarListing;
use App\Models\CarMaker;
use App\Models\CarModel;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Services\BookmarkService;
use App\Services\ListingImageService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use LogicException;

class DemoSeeder extends Seeder
{
    public const string MARKER_EMAIL = 'seller01@demo.test';

    public const string SUSPENDED_EMAIL = 'seller30@demo.test';

    /** @var list<string> */
    private const array ENVIRONMENTS = ['local', 'testing'];

    private const int USERS = 30;

    private const int APPROVED_LISTINGS = 31;

    private const int PENDING_LISTINGS = 3;

    private const int REJECTED_LISTINGS = 3;

    private const int SUSPENDED_OWNER_LISTINGS = 2;

    private const int BOOKMARKERS = 10;

    private const int BOOKMARKS_EACH = 3;

    private const int CONVERSATIONS = 10;

    /** @var array<int, int> */
    private const array PHOTOS_BY_LISTING = [0 => 8, 1 => 3, 2 => 2, 3 => 2];

    /** @var list<array{int, int}> */
    private const array PHOTO_SIZES = [[1200, 800], [800, 1200], [1000, 1000]];

    /** @var list<array{int, int, int}> */
    private const array PHOTO_COLOURS = [
        [194, 45, 36], [31, 94, 107], [201, 152, 52], [58, 52, 48],
        [122, 140, 92], [166, 92, 60], [72, 96, 150], [140, 60, 90],
    ];

    /** @var list<string> */
    private const array REJECTION_REASONS = [
        'The photos do not show the car clearly.',
        'The price looks like a typo. Please check it and resubmit.',
        'The description is too short to tell the car apart.',
    ];

    /** @var list<string> */
    private const array MESSAGES = [
        'Hello! Is the car still available?',
        'Yes, it is. Would you like to see it this weekend?',
        'Saturday morning works for me. Where is it parked?',
        'In my garage near the old harbour. I will send the address.',
        'Does it come with the original service records?',
        'Most of them, from 1974 onwards, in a blue folder.',
    ];

    public function run(): void
    {
        if (! app()->environment(self::ENVIRONMENTS)) {
            $this->command?->warn('DemoSeeder runs only in the '.implode(' or ', self::ENVIRONMENTS).' environment. Nothing was written.');

            return;
        }

        if (User::query()->where('email', self::MARKER_EMAIL)->exists()) {
            return;
        }

        $models = $this->models();
        $moderatorId = User::query()->where('role', UserRole::Admin)->value('id');
        $users = $this->seedUsers();
        $suspendedOwner = $users->pop();
        $listings = $this->seedListings($users, $models, $moderatorId);
        $this->seedCustomModelListing($users->first(), $models->first()->car_maker_id);
        $this->seedSuspendedOwnerListings($suspendedOwner, $models, $moderatorId);
        $this->seedBookmarks($users);
        $this->seedConversations($users, $listings);
    }

    /**
     * @return Collection<int, User>
     */
    private function seedUsers(): Collection
    {
        return collect(range(1, self::USERS))->map(function (int $number): User {
            $padded = str_pad((string) $number, 2, '0', STR_PAD_LEFT);
            $user = new User([
                'username' => "Demo Seller {$padded}",
                'email' => "seller{$padded}@demo.test",
                'phone_number' => '+535556'.str_pad((string) $number, 4, '0', STR_PAD_LEFT),
                'password' => 'password',
            ]);
            $user->role = UserRole::AppUser;
            $user->status = UserStatus::Active;

            if ($number === self::USERS) {
                $user->status = UserStatus::Suspended;
                $user->suspended_at = now();
                $user->suspended_until = now()->addDays(30);
                $user->suspension_reason = 'Repeated abusive messages.';
            }

            $user->save();

            return $user;
        });
    }

    /**
     * @param  Collection<int, User>  $owners
     * @param  Collection<int, CarModel>  $models
     * @return list<CarListing>
     */
    private function seedListings(Collection $owners, Collection $models, ?int $moderatorId): array
    {
        $total = self::APPROVED_LISTINGS + self::PENDING_LISTINGS + self::REJECTED_LISTINGS;
        $step = max(1, intdiv($models->count(), $total));
        $approved = [];

        foreach (range(0, $total - 1) as $index) {
            $createdAt = now()->subDays($index + 1)->subHours(($index * 5) % 24);
            $factory = CarListing::factory()->forCarModel($models[($index * $step) % $models->count()])
                ->for($owners[$index % $owners->count()], 'owner');

            if ($index < self::APPROVED_LISTINGS) {
                $listing = $factory->approved()->create([
                    'moderated_by' => $moderatorId,
                    'created_at' => $createdAt,
                    'submitted_at' => $createdAt,
                    'approved_at' => $createdAt->copy()->addHours(8),
                ]);
                $approved[] = $listing;
            } elseif ($index < self::APPROVED_LISTINGS + self::PENDING_LISTINGS) {
                $listing = $factory->create(['created_at' => $createdAt, 'submitted_at' => $createdAt]);
            } else {
                $reason = self::REJECTION_REASONS[$index % count(self::REJECTION_REASONS)];
                $listing = $factory->rejected($reason)->create([
                    'moderated_by' => $moderatorId,
                    'created_at' => $createdAt,
                    'submitted_at' => $createdAt,
                    'rejected_at' => $createdAt->copy()->addHours(8),
                ]);
            }

            $this->seedImages($listing, self::PHOTOS_BY_LISTING[$index] ?? 1);
        }

        return $approved;
    }

    private function seedCustomModelListing(User $owner, int $makerId): void
    {
        $submittedAt = now()->subDays(3);
        $listing = CarListing::factory()->customModel()->for($owner, 'owner')->create([
            'car_maker_id' => $makerId,
            'name' => 'Coachbuilt Speedster',
            'model' => 'Speedster',
            'submitted_at' => $submittedAt,
            'created_at' => $submittedAt,
        ]);

        $this->seedImages($listing, 1);
    }

    /**
     * @param  Collection<int, CarModel>  $models
     */
    private function seedSuspendedOwnerListings(User $owner, Collection $models, ?int $moderatorId): void
    {
        foreach (range(1, self::SUSPENDED_OWNER_LISTINGS) as $number) {
            $createdAt = now()->subDays(10 + $number);
            $listing = CarListing::factory()->approved()->forCarModel($models[$models->count() - $number])->for($owner, 'owner')
                ->create([
                    'moderated_by' => $moderatorId,
                    'created_at' => $createdAt,
                    'submitted_at' => $createdAt,
                    'approved_at' => $createdAt->copy()->addHours(8),
                ]);

            $this->seedImages($listing, 1);
        }
    }

    /**
     * @param  Collection<int, User>  $users
     */
    private function seedBookmarks(Collection $users): void
    {
        $bookmarks = app(BookmarkService::class);

        foreach ($users->take(self::BOOKMARKERS) as $offset => $user) {
            $listings = CarListing::query()->publiclyVisible()->whereNot('car_listings.user_id', $user->id)
                ->orderBy('car_listings.id')->skip($offset * self::BOOKMARKS_EACH)->limit(self::BOOKMARKS_EACH)->get();

            foreach ($listings as $listing) {
                $bookmarks->add($user, $listing);
            }
        }
    }

    /**
     * @param  Collection<int, User>  $users
     * @param  list<CarListing>  $approved
     */
    private function seedConversations(Collection $users, array $approved): void
    {
        $now = CarbonImmutable::now();

        foreach (range(0, self::CONVERSATIONS - 1) as $index) {
            $listing = $approved[($index * 3) % count($approved)];
            $buyer = $users[$index]->id === $listing->user_id ? $users[$index + 1] : $users[$index];
            $startedAt = $now->subDays($index + 1);
            $count = 2 + ($index % 3);
            $sentAt = fn (int $position): CarbonImmutable => $startedAt->addHours($position);

            $conversation = Conversation::factory()->create([
                'listing_id' => $listing->id,
                'sender_id' => $buyer->id,
                'receiver_id' => $listing->user_id,
                'last_message_at' => $sentAt($count - 1),
                'created_at' => $startedAt,
                'updated_at' => $startedAt,
            ]);

            foreach (range(0, $count - 1) as $position) {
                $isLast = $position === $count - 1;

                Message::factory()->create([
                    'conversation_id' => $conversation->id,
                    'sender_id' => $position % 2 === 0 ? $buyer->id : $listing->user_id,
                    'message' => self::MESSAGES[$position % count(self::MESSAGES)],
                    'read_at' => $isLast && $index % 2 === 0 ? null : $sentAt($position)->addMinutes(30),
                    'created_at' => $sentAt($position),
                    'updated_at' => $sentAt($position),
                ]);
            }
        }
    }

    private function seedImages(CarListing $listing, int $count): void
    {
        $files = array_map(fn (int $number): UploadedFile => $this->photo($number), range(1, $count));

        try {
            DB::transaction(fn () => app(ListingImageService::class)->storeForNewListing($listing, $files));
        } finally {
            File::delete(array_map(fn (UploadedFile $file): string => $file->getPathname(), $files));
        }
    }

    private function photo(int $number): UploadedFile
    {
        [$width, $height] = self::PHOTO_SIZES[($number - 1) % count(self::PHOTO_SIZES)];
        [$red, $green, $blue] = self::PHOTO_COLOURS[($number - 1) % count(self::PHOTO_COLOURS)];

        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, imagecolorallocate($image, $red, $green, $blue));

        $digit = imagecreatetruecolor(9, 15);
        imagefill($digit, 0, 0, imagecolorallocate($digit, $red, $green, $blue));
        imagestring($digit, 5, 0, 0, (string) $number, imagecolorallocate($digit, 245, 236, 215));
        imagecopyresized($image, $digit, intdiv($width - 180, 2), intdiv($height - 300, 2), 0, 0, 180, 300, 9, 15);

        $path = tempnam(sys_get_temp_dir(), 'demo');
        imagejpeg($image, $path, 85);

        return new UploadedFile($path, "demo-{$number}.jpg", 'image/jpeg', null, true);
    }

    /**
     * @return Collection<int, CarModel>
     */
    private function models(): Collection
    {
        $models = CarMaker::query()->active()->ordered()->with('activeModels')->get()
            ->flatMap(fn (CarMaker $maker): Collection => $maker->activeModels)
            ->values();

        if ($models->count() < self::APPROVED_LISTINGS) {
            throw new LogicException('DemoSeeder needs the seeded makers and models: run `php artisan migrate:fresh --seed` first.');
        }

        return $models;
    }
}
