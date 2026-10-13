<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\CarListing;
use App\Models\CarMaker;
use App\Models\CarModel;
use App\Models\User;
use App\Services\BookmarkService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use LogicException;

class ReviewSeeder extends Seeder
{
    private const int APPROVED_LISTINGS = 14;

    private const int STRANGER_LISTINGS = 13;

    private const int BOOKMARKS = 13;

    private const string STRANGER_USERNAME = 'Cristobal Ignacio de la Santisima Trinidad Valdes y Hernandez';

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

        foreach (range(0, self::APPROVED_LISTINGS - 1) as $index) {
            $factory = CarListing::factory()->approved()->forCarModel($models[$index % $models->count()]);

            if ($index === 0) {
                $factory = $factory->withImages(3);
            }

            $factory->for($sellers[$index % count($sellers)], 'owner')
                ->create(['moderated_by' => $moderatorId, 'created_at' => now()->subDays($index + 1)]);
        }

        CarListing::factory()->forCarModel($models[0])->for($reviewer, 'owner')->create();
        CarListing::factory()->rejected('The photos do not show the car clearly.')->forCarModel($models[1])->for($reviewer, 'owner')
            ->create(['moderated_by' => $moderatorId]);

        return CarListing::factory()->approved()->forCarModel($models[2])->for(User::factory()->suspended(), 'owner')
            ->create(['moderated_by' => $moderatorId]);
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
