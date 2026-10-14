<?php

namespace Tests\Feature\Account;

use App\Models\CarListing;
use App\Models\User;
use App\Services\AccountDeletionService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use LogicException;
use RuntimeException;
use Tests\TestCase;

class AccountDeletionServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_it_deletes_an_app_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        app(AccountDeletionService::class)->delete($user);

        $this->assertModelMissing($user);
        $this->assertModelExists($other);
    }

    public function test_it_removes_the_users_pending_reset_token(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        Password::broker()->createToken($user);
        Password::broker()->createToken($other);

        app(AccountDeletionService::class)->delete($user);

        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
        $this->assertDatabaseHas('password_reset_tokens', ['email' => $other->email]);
    }

    public function test_it_removes_the_users_listing_directories_and_keeps_other_users_files(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $listings = CarListing::factory()->for($user, 'owner')->count(2)->create();
        $otherListing = CarListing::factory()->create();
        foreach ([...$listings, $otherListing] as $listing) {
            Storage::disk('public')->put("listings/{$listing->id}/photo.webp", 'x');
        }

        app(AccountDeletionService::class)->delete($user);

        foreach ($listings as $listing) {
            $this->assertFalse(Storage::disk('public')->directoryExists("listings/{$listing->id}"));
        }
        Storage::disk('public')->assertExists("listings/{$otherListing->id}/photo.webp");
    }

    public function test_a_rolled_back_deletion_keeps_the_listing_directories(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $listing = CarListing::factory()->for($user, 'owner')->create();
        Storage::disk('public')->put("listings/{$listing->id}/photo.webp", 'x');

        try {
            DB::transaction(function () use ($user): void {
                app(AccountDeletionService::class)->delete($user);

                throw new RuntimeException('The enclosing transaction failed.');
            });
        } catch (RuntimeException) {
        }

        $this->assertModelExists($user);
        Storage::disk('public')->assertExists("listings/{$listing->id}/photo.webp");
    }

    public function test_it_refuses_to_delete_an_admin(): void
    {
        $admin = User::factory()->admin()->create();

        try {
            app(AccountDeletionService::class)->delete($admin);
            $this->fail('An admin account was deleted.');
        } catch (LogicException) {
            $this->assertModelExists($admin);
        }
    }
}
