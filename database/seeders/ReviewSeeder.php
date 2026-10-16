<?php

namespace Database\Seeders;

use App\Enums\ReportReason;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\CarListing;
use App\Models\CarMaker;
use App\Models\CarModel;
use App\Models\ContactMessage;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\MessageReport;
use App\Models\User;
use App\Services\BookmarkService;
use App\Services\ListingImageService;
use Carbon\CarbonInterface;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use LogicException;

class ReviewSeeder extends Seeder
{
    private const int APPROVED_LISTINGS = 14;

    private const int STRANGER_LISTINGS = 13;

    private const int BOOKMARKS = 13;

    private const string STRANGER_USERNAME = 'Cristobal Ignacio de la Santisima Trinidad Valdes y Hernandez';

    private const string LONG_MESSAGE = 'I would love to see it on Saturday morning. Could you also bring the service records and the titles?';

    private const int IMAGES_APPROVED = 8;

    private const int IMAGES_PENDING = 2;

    private const int IMAGES_REJECTED = 1;

    /** @var list<array{int, int}> */
    private const array PHOTO_SIZES = [[1200, 800], [800, 1200], [1000, 1000]];

    /** @var list<array{int, int, int}> */
    private const array PHOTO_COLOURS = [
        [194, 45, 36], [31, 94, 107], [201, 152, 52], [58, 52, 48],
        [122, 140, 92], [166, 92, 60], [72, 96, 150], [140, 60, 90],
    ];

    public function run(): void
    {
        if (User::query()->where('email', 'review@example.com')->exists()) {
            return;
        }

        $models = $this->models();
        $user = $this->appUser('review user', 'review@example.com', '+5355512345');
        $stranger = $this->appUser(self::STRANGER_USERNAME, 'stranger@example.com', '+5355554321');
        $moderatorId = User::query()->where('role', UserRole::Admin)->value('id');
        ['photographed' => $photographedListing, 'suspended' => $suspendedListing] = $this->seedListings($user, $models, $moderatorId);
        $this->seedStrangerListings($stranger, $models, $moderatorId);
        $this->seedBookmarks($user, $suspendedListing);
        $strangerMessage = $this->seedConversation($user, $stranger);
        $buyer = $this->appUser('Lucia Fernandez', 'buyer@example.com', '+5355500111');
        $reviewerReply = $this->seedSellerConversation($photographedListing, $user, $buyer);
        $this->seedCustomModelListing($stranger, $models[0]->car_maker_id);
        $this->seedReports($strangerMessage, $user, $reviewerReply, $buyer, $moderatorId);
        $this->seedContactMessages($user, $buyer, $moderatorId);
    }

    private function appUser(string $username, string $email, string $phone): User
    {
        $user = new User([
            'username' => $username,
            'email' => $email,
            'phone_number' => $phone,
            'password' => 'password',
        ]);
        $user->role = UserRole::AppUser;
        $user->status = UserStatus::Active;
        $user->save();

        return $user;
    }

    /**
     * @param  Collection<int, CarModel>  $models
     * @return array{photographed: CarListing, suspended: CarListing}
     */
    private function seedListings(User $reviewer, Collection $models, ?int $moderatorId): array
    {
        $sellers = [$reviewer, User::factory()->create(), User::factory()->create()];

        $approved = [];

        foreach (range(0, self::APPROVED_LISTINGS - 1) as $index) {
            $approved[] = CarListing::factory()->approved()->forCarModel($models[$index % $models->count()])
                ->for($sellers[$index % count($sellers)], 'owner')
                ->create(['moderated_by' => $moderatorId, 'created_at' => now()->subDays($index + 1)]);
        }

        $pending = CarListing::factory()->forCarModel($models[0])->for($reviewer, 'owner')->create();
        $rejected = CarListing::factory()->rejected('The photos do not show the car clearly.')->forCarModel($models[1])->for($reviewer, 'owner')
            ->create(['moderated_by' => $moderatorId]);

        $this->seedImages($approved[0], self::IMAGES_APPROVED);
        $this->seedImages($pending, self::IMAGES_PENDING);
        $this->seedImages($rejected, self::IMAGES_REJECTED);

        $suspended = CarListing::factory()->approved()->forCarModel($models[2])->for(User::factory()->suspended(now()->addDays(30)), 'owner')
            ->create(['moderated_by' => $moderatorId]);

        return ['photographed' => $approved[0], 'suspended' => $suspended];
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

        $path = tempnam(sys_get_temp_dir(), 'review');
        imagejpeg($image, $path, 85);

        return new UploadedFile($path, "review-{$number}.jpg", 'image/jpeg', null, true);
    }

    /**
     * @param  Collection<int, CarModel>  $models
     */
    private function seedStrangerListings(User $stranger, Collection $models, ?int $moderatorId): void
    {
        foreach (range(0, self::STRANGER_LISTINGS - 1) as $index) {
            CarListing::factory()->approved()->forCarModel($models[($index + 1) % $models->count()])->for($stranger, 'owner')
                ->create(['moderated_by' => $moderatorId, 'created_at' => now()->subHours($index + 1)]);
        }
    }

    private function seedBookmarks(User $reviewer, CarListing $suspendedListing): void
    {
        $bookmarks = app(BookmarkService::class);
        $listings = CarListing::query()->publiclyVisible()->whereNot('car_listings.user_id', $reviewer->id)
            ->orderByDesc('car_listings.id')->limit(self::BOOKMARKS)->get();

        foreach ([...$listings, $suspendedListing] as $listing) {
            $bookmarks->add($reviewer, $listing);
        }
    }

    private function seedConversation(User $buyer, User $seller): Message
    {
        $listing = $seller->listings()->latest('id')->firstOrFail();
        $now = now()->toImmutable();

        return $this->seedThread($listing, $buyer, $seller, [
            [$buyer, 'Hello! Is the car still available?', $now->subHours(3), $now->subHours(2)],
            [$seller, 'Yes, it is. Would you like to see it this weekend?', $now->subHours(2), $now->subHour()],
            [$buyer, self::LONG_MESSAGE, $now->subHour(), $now->subMinutes(30)],
            [$seller, 'Saturday at ten works for me. I will send you the address.', $now->subMinutes(5), null],
        ])[3];
    }

    private function seedSellerConversation(CarListing $listing, User $seller, User $buyer): Message
    {
        $now = now()->toImmutable();

        return $this->seedThread($listing, $buyer, $seller, [
            [$buyer, 'Good afternoon. Does the car have its original engine?', $now->subDays(3), $now->subDays(3)->addHour()],
            [$seller, 'It does, rebuilt in 2019 with every receipt kept.', $now->subDays(3)->addHours(2), $now->subDays(2)->subHour()],
            [$buyer, 'Thank you, I will think it over and write again soon.', $now->subDays(2), $now->subDays(2)->addMinutes(30)],
        ])[1];
    }

    private function seedCustomModelListing(User $owner, int $makerId): void
    {
        $submittedAt = now()->subDays(2);
        $listing = CarListing::factory()->customModel()->for($owner, 'owner')->create([
            'car_maker_id' => $makerId,
            'name' => 'Custom Roadster',
            'model' => 'Roadster',
            'submitted_at' => $submittedAt,
            'created_at' => $submittedAt,
        ]);

        $this->seedImages($listing, 1);
    }

    private function seedReports(Message $strangerMessage, User $reviewer, Message $reviewerReply, User $buyer, ?int $moderatorId): void
    {
        $now = now()->toImmutable();

        MessageReport::factory()->create([
            'message_id' => $strangerMessage->id,
            'reporter_id' => $reviewer->id,
            'reason' => ReportReason::Other,
            'note' => 'He keeps asking me to pay a deposit outside the app.',
            'created_at' => $now->subMinutes(2),
            'updated_at' => $now->subMinutes(2),
        ]);

        MessageReport::factory()->resolved()->create([
            'message_id' => $reviewerReply->id,
            'reporter_id' => $buyer->id,
            'reason' => ReportReason::Scam,
            'resolved_by' => $moderatorId,
            'resolved_at' => $now->subDay(),
            'created_at' => $now->subDays(2),
            'updated_at' => $now->subDay(),
        ]);
    }

    private function seedContactMessages(User $reviewer, User $buyer, ?int $moderatorId): void
    {
        $now = now()->toImmutable();

        ContactMessage::factory()->for($reviewer)->create([
            'subject' => 'How long does approval usually take?',
            'message' => "I listed my 1957 Bel Air two days ago and it is still pending.\nIs there anything I should add to the listing?",
            'created_at' => $now->subHour(),
            'updated_at' => $now->subHour(),
        ]);

        ContactMessage::factory()->handled()->for($buyer)->create([
            'subject' => 'Changing my phone number',
            'message' => 'Could you tell me how to update the phone number shown on my account?',
            'handled_by' => $moderatorId,
            'handled_at' => $now->subDay(),
            'created_at' => $now->subDays(2),
            'updated_at' => $now->subDay(),
        ]);
    }

    /**
     * @param  non-empty-list<array{User, string, CarbonInterface, ?CarbonInterface}>  $messages
     * @return list<Message>
     */
    private function seedThread(CarListing $listing, User $buyer, User $seller, array $messages): array
    {
        $startedAt = $messages[0][2];
        $conversation = Conversation::factory()->create([
            'listing_id' => $listing->id,
            'sender_id' => $buyer->id,
            'receiver_id' => $seller->id,
            'last_message_at' => $messages[array_key_last($messages)][2],
            'created_at' => $startedAt,
            'updated_at' => $startedAt,
        ]);

        $created = [];

        foreach ($messages as [$sender, $text, $sentAt, $readAt]) {
            $created[] = Message::factory()->create([
                'conversation_id' => $conversation->id,
                'sender_id' => $sender->id,
                'message' => $text,
                'read_at' => $readAt,
                'created_at' => $sentAt,
                'updated_at' => $sentAt,
            ]);
        }

        return $created;
    }

    /**
     * @return Collection<int, CarModel>
     */
    private function models(): Collection
    {
        $models = CarMaker::query()->active()->ordered()->with('activeModels')->limit(6)->get()
            ->flatMap(fn (CarMaker $maker): Collection => $maker->activeModels->take(3))
            ->values();

        if ($models->count() < 3) {
            throw new LogicException('ReviewSeeder needs the seeded makers and models: run `php artisan migrate:fresh --seed` first.');
        }

        return $models;
    }
}
