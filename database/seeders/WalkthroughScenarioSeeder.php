<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\CarListing;
use App\Models\CarModel;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Services\ListingImageService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use LogicException;

class WalkthroughScenarioSeeder extends Seeder
{
    public const string SELLER_EMAIL = 'seller@walkthrough.test';

    public const string ABUSER_EMAIL = 'abuser@walkthrough.test';

    public const string EXPIRED_OWNER_EMAIL = 'expired@walkthrough.test';

    public const string BUYER_EMAIL = 'buyer@walkthrough.test';

    public const string PASSWORD = 'password';

    /** @var list<string> */
    private const array ENVIRONMENTS = ['local', 'testing'];

    private const string WALKED_MODEL = 'Bel Air';

    private const string WALKED_MAKER = 'Chevrolet';

    private const string REJECTION_REASON = 'The mileage and the description disagree. Please check them and resubmit.';

    /** @var list<array{int, int, int}> */
    private const array PHOTO_COLOURS = [[31, 94, 107], [201, 152, 52], [122, 140, 92], [72, 96, 150], [140, 60, 90]];

    public function run(): void
    {
        if (! app()->environment(self::ENVIRONMENTS)) {
            $this->command?->error('WalkthroughScenarioSeeder runs only in the '.implode(' or ', self::ENVIRONMENTS).' environment. Nothing was written.');

            return;
        }

        if (User::query()->whereIn('email', $this->emails())->exists()) {
            $this->command?->warn('Walkthrough accounts already exist. Run `php artisan migrate:fresh --seed` first. Nothing was written.');

            return;
        }

        $this->call([ReviewSeeder::class, DemoSeeder::class]);

        $models = $this->models();
        $moderatorId = User::query()->where('role', UserRole::Admin)->value('id');

        $seller = $this->appUser('Walkthrough Seller', self::SELLER_EMAIL, '+5355570001');
        $abuser = $this->appUser('Walkthrough Abuser', self::ABUSER_EMAIL, '+5355570002');
        $expiredOwner = $this->appUser('Walkthrough Expired Owner', self::EXPIRED_OWNER_EMAIL, '+5355570003', CarbonImmutable::now()->subHour());

        $abuserListings = [
            $this->listing($abuser, $models[0], $moderatorId, approved: true),
            $this->listing($abuser, $models[1], $moderatorId, approved: true),
        ];
        $this->listing($expiredOwner, $models[2], $moderatorId, approved: true);
        $this->listing($expiredOwner, $models[3], $moderatorId, approved: true);
        $this->listing($expiredOwner, $models[4], $moderatorId, approved: false);

        $this->seedThread($seller, $abuser, $abuserListings[0]);

        $this->printInputs();
    }

    /**
     * @return list<string>
     */
    private function emails(): array
    {
        return [self::SELLER_EMAIL, self::ABUSER_EMAIL, self::EXPIRED_OWNER_EMAIL, self::BUYER_EMAIL];
    }

    private function appUser(string $username, string $email, string $phone, ?CarbonImmutable $suspendedUntil = null): User
    {
        $user = new User([
            'username' => $username,
            'email' => $email,
            'phone_number' => $phone,
            'password' => self::PASSWORD,
        ]);
        $user->role = UserRole::AppUser;
        $user->status = UserStatus::Active;

        if ($suspendedUntil !== null) {
            $user->status = UserStatus::Suspended;
            $user->suspended_at = $suspendedUntil->subDays(7);
            $user->suspended_until = $suspendedUntil;
            $user->suspension_reason = 'Repeated abusive messages.';
        }

        $user->save();

        return $user;
    }

    private function listing(User $owner, CarModel $model, ?int $moderatorId, bool $approved): CarListing
    {
        $factory = CarListing::factory()->forCarModel($model)->for($owner, 'owner');
        $factory = $approved ? $factory->approved() : $factory->rejected(self::REJECTION_REASON);
        $listing = $factory->create(['moderated_by' => $moderatorId]);

        $this->seedImage($listing);

        return $listing;
    }

    private function seedThread(User $seller, User $abuser, CarListing $listing): void
    {
        $now = CarbonImmutable::now();
        $conversation = Conversation::factory()->create([
            'listing_id' => $listing->id,
            'sender_id' => $seller->id,
            'receiver_id' => $abuser->id,
            'last_message_at' => $now->subHours(2),
            'created_at' => $now->subHours(3),
            'updated_at' => $now->subHours(3),
        ]);

        foreach ([
            [$seller, 'Hello, is the car still for sale? I would like to see it.', $now->subHours(3)],
            [$abuser, 'Stop bothering me, you clueless time waster. Go away.', $now->subHours(2)],
        ] as [$sender, $text, $sentAt]) {
            Message::factory()->create([
                'conversation_id' => $conversation->id,
                'sender_id' => $sender->id,
                'message' => $text,
                'read_at' => $sentAt->addMinutes(30),
                'created_at' => $sentAt,
                'updated_at' => $sentAt,
            ]);
        }
    }

    private function seedImage(CarListing $listing): void
    {
        $colour = self::PHOTO_COLOURS[$listing->id % count(self::PHOTO_COLOURS)];
        $image = imagecreatetruecolor(1200, 800);
        imagefill($image, 0, 0, imagecolorallocate($image, ...$colour));
        $path = tempnam(sys_get_temp_dir(), 'walk');
        imagejpeg($image, $path, 85);
        $file = new UploadedFile($path, 'walkthrough.jpg', 'image/jpeg', null, true);

        try {
            DB::transaction(fn () => app(ListingImageService::class)->storeForNewListing($listing, [$file]));
        } finally {
            File::delete($path);
        }
    }

    /**
     * @return Collection<int, CarModel>
     */
    private function models(): Collection
    {
        $models = CarModel::query()->active()->ordered()
            ->whereHas('maker', fn (Builder $maker) => $maker->active())
            ->whereNot('name', self::WALKED_MODEL)
            ->limit(5)
            ->get();

        if ($models->count() < 5) {
            throw new LogicException('WalkthroughScenarioSeeder needs the seeded makers and models: run `php artisan migrate:fresh --seed` first.');
        }

        return $models;
    }

    private function printInputs(): void
    {
        $command = $this->command;

        if ($command === null) {
            return;
        }

        $accounts = User::query()->whereIn('email', $this->emails())->orderBy('id')->get();
        $command->newLine();
        $command->info('Walkthrough inputs (password for every account: '.self::PASSWORD.')');
        $command->table(['Email', 'Role', 'Status', 'Suspended until'], [
            ...$accounts->map(fn (User $user): array => [$user->email, $user->role->value, $user->status->value, $user->suspended_until?->toDateTimeString() ?? '—']),
            [self::BUYER_EMAIL, '—', 'left free: the guest registers with it', '—'],
        ]);

        $abuser = $accounts->firstWhere('email', self::ABUSER_EMAIL);
        $command->line('Abuser listings:');
        foreach ($abuser->listings()->orderBy('id')->get() as $listing) {
            $command->line("  {$listing->status->value}  ".route('listings.show', $listing)."  {$listing->name}");
        }

        $thread = Conversation::query()->whereIn('listing_id', $abuser->listings()->select('id'))->sole();
        $command->line('Seller ↔ abuser thread: '.route('conversations.show', $thread));

        $expiredOwner = $accounts->firstWhere('email', self::EXPIRED_OWNER_EMAIL);
        $command->line('Expired owner listings:');
        foreach ($expiredOwner->listings()->orderBy('id')->get() as $listing) {
            $command->line("  {$listing->status->value}  ".route('listings.show', $listing)."  {$listing->name}");
        }

        $walkedModel = CarModel::query()->where('name', self::WALKED_MODEL)
            ->whereHas('maker', fn (Builder $maker) => $maker->where('name', self::WALKED_MAKER))->sole();
        $command->line(self::WALKED_MAKER.' '.self::WALKED_MODEL." years: {$walkedModel->year_from}–{$walkedModel->year_to}");

        $partner = CarListing::query()->publiclyVisible()
            ->whereHas('maker', fn (Builder $maker) => $maker->whereNot('name', self::WALKED_MAKER))
            ->has('images', '>=', 2)
            ->with('maker')
            ->orderBy('car_listings.id')
            ->first();
        $command->line('Compare partner: '.($partner === null ? 'none found' : route('listings.show', $partner)."  {$partner->maker->name} · {$partner->name}"));
    }
}
