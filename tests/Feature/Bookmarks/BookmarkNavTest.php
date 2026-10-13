<?php

namespace Tests\Feature\Bookmarks;

use App\Models\CarListing;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class BookmarkNavTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_a_member_gets_an_uncounted_bookmarks_link_that_is_current_on_the_bookmarks_page(): void
    {
        $user = User::factory()->create();
        $user->bookmarks()->attach(CarListing::factory()->approved()->count(2)->create()->modelKeys());

        $response = $this->actingAs($user)->get(route('bookmarks.index'));

        $response->assertSee('data-header-variant="member"', false)
            ->assertDontSee('data-nav-count', false);
        $this->assertMatchesRegularExpression('/<a href="'.preg_quote(route('bookmarks.index'), '/').'"\s+aria-current="page"/', (string) $response->getContent());
    }

    public function test_a_guest_gets_no_bookmarks_link(): void
    {
        $response = $this->get(route('home'));

        $response->assertSee('data-header-variant="guest"', false)
            ->assertDontSee('<a href="'.route('bookmarks.index').'"', false);
    }

    public function test_the_admin_header_has_no_bookmarks_link(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $response = $this->get(route('bookmarks.index'));

        $response->assertOk()
            ->assertSee('data-header-variant="admin"', false)
            ->assertDontSee('<a href="'.route('bookmarks.index').'"', false);
    }
}
