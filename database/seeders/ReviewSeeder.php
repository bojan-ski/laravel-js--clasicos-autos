<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\CarListing;
use App\Models\CarMaker;
use App\Models\CarModel;
use App\Models\User;
use App\Services\BookmarkService;
use App\Services\ListingImageService;
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
        $suspendedListing = $this->seedListings($user, $models, $moderatorId);
        $this->seedStrangerListings($stranger, $models, $moderatorId);
        $this->seedBookmarks($user, $suspendedListing);
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
     */
    private function seedListings(User $reviewer, Collection $models, ?int $moderatorId): CarListing
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

        return CarListing::factory()->approved()->forCarModel($models[2])->for(User::factory()->suspended(), 'owner')
            ->create(['moderated_by' => $moderatorId]);
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
