<?php

namespace Tests\Feature\Bookmarks;

use App\Models\CarListing;
use App\Models\User;
use App\Services\BookmarkService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class BookmarkServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_add_and_remove_keep_the_loaded_ids_in_step_for_that_user_only(): void
    {
        $listing = CarListing::factory()->approved()->create();
        $user = User::factory()->create();
        $other = User::factory()->create();
        $bookmarks = app(BookmarkService::class);

        $this->assertFalse($bookmarks->isBookmarked($user, $listing));
        $this->assertFalse($bookmarks->isBookmarked($other, $listing));

        $bookmarks->add($user, $listing);

        $this->assertTrue($bookmarks->isBookmarked($user, $listing));
        $this->assertFalse($bookmarks->isBookmarked($other, $listing));

        $bookmarks->remove($user, $listing);

        $this->assertFalse($bookmarks->isBookmarked($user, $listing));
    }
}
