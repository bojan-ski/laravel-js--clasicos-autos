<?php

namespace Tests\Feature\Account;

use App\Models\CarListing;
use App\Models\User;
use App\Services\AccountDeletionService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class DeleteAccountTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function rejectedPasswords(): array
    {
        return [
            'wrong password' => [['delete_password' => 'not-my-password']],
            'empty password' => [['delete_password' => '']],
            'missing password' => [[]],
            'password as an array' => [['delete_password' => ['password']]],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    #[DataProvider('rejectedPasswords')]
    public function test_rejected_password_flashes_the_error_and_keeps_the_account(array $payload): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from('https://evil.example/phish')
            ->delete(route('profile.destroy'), $payload)
            ->assertRedirect(route('profile'))
            ->assertSessionHas('error', __('app.profile.delete.failed'))
            ->assertSessionHasNoErrors()
            ->assertSessionMissing('_old_input');

        $this->assertModelExists($user);
        $this->assertAuthenticatedAs($user);

        $this->get(route('profile'))
            ->assertOk()
            ->assertSee(__('app.profile.delete.failed'))
            ->assertDontSee('id="delete_password-error"', false);
    }

    public function test_correct_password_deletes_the_account_and_signs_out(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get(route('profile'))->assertOk();
        $tokenBefore = session()->token();

        $this->delete(route('profile.destroy'), ['delete_password' => 'password'])
            ->assertRedirect(route('home'))
            ->assertSessionHas('success', __('app.profile.delete.deleted'));

        $this->assertModelMissing($user);
        $this->assertGuest();
        $this->assertNotSame($tokenBefore, session()->token());

        $this->get(route('home'))
            ->assertOk()
            ->assertSee(__('app.profile.delete.deleted'));
    }

    public function test_deleting_the_account_removes_its_listing_photos_only(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $listing = CarListing::factory()->for($user, 'owner')->create();
        $otherListing = CarListing::factory()->create();
        Storage::disk('public')->put("listings/{$listing->id}/photo.webp", 'x');
        Storage::disk('public')->put("listings/{$otherListing->id}/photo.webp", 'x');

        $this->actingAs($user)
            ->delete(route('profile.destroy'), ['delete_password' => 'password'])
            ->assertRedirect(route('home'));

        $this->assertFalse(Storage::disk('public')->directoryExists("listings/{$listing->id}"));
        Storage::disk('public')->assertExists("listings/{$otherListing->id}/photo.webp");
    }

    public function test_a_failing_deletion_still_ends_the_session(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get(route('profile'))->assertOk();
        $tokenBefore = session()->token();
        $this->mock(AccountDeletionService::class)
            ->shouldReceive('delete')
            ->andThrow(new RuntimeException('Deletion failed.'));

        $this->delete(route('profile.destroy'), ['delete_password' => 'password'])
            ->assertInternalServerError();

        $this->assertGuest();
        $this->assertNotSame($tokenBefore, session()->token());
        $this->assertModelExists($user);
    }

    public function test_deleting_a_user_with_a_remember_token_does_not_reinsert_the_row(): void
    {
        $user = User::factory()->create(['remember_token' => Str::random(60)]);

        $this->actingAs($user)
            ->delete(route('profile.destroy'), ['delete_password' => 'password'])
            ->assertRedirect(route('home'));

        $this->assertModelMissing($user);
        $this->assertDatabaseCount('users', 0);
    }
}
