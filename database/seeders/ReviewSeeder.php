<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\CarListing;
use App\Models\CarMaker;
use App\Models\CarModel;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use LogicException;

class ReviewSeeder extends Seeder
{
    private const int APPROVED_LISTINGS = 14;

    public function run(): void
    {
        if (User::query()->where('email', 'review@example.com')->exists()) {
            return;
        }

        $user = new User([
            'username' => 'review user',
            'email' => 'review@example.com',
            'phone_number' => '+5355512345',
            'password' => 'password',
        ]);
        $user->role = UserRole::AppUser;
        $user->status = UserStatus::Active;
        $user->save();

        $this->seedListings($user);
    }

    private function seedListings(User $reviewer): void
    {
        $models = $this->models();
        $sellers = [$reviewer, User::factory()->create(), User::factory()->create()];
        $moderatorId = User::query()->where('role', UserRole::Admin)->value('id');

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

        CarListing::factory()->approved()->forCarModel($models[2])->for(User::factory()->suspended(), 'owner')
            ->create(['moderated_by' => $moderatorId]);
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
