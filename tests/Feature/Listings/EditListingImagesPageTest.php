<?php

namespace Tests\Feature\Listings;

use App\Enums\ListingStatus;
use App\Models\CarListing;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EditListingImagesPageTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_a_guest_is_sent_to_login(): void
    {
        $listing = CarListing::factory()->approved()->create();

        $this->get(route('listings.images.edit', $listing))->assertRedirect(route('login'));
    }

    public function test_a_stranger_gets_the_403_page_on_a_public_listing(): void
    {
        $listing = CarListing::factory()->approved()->create();

        $this->actingAs(User::factory()->create())->get(route('listings.images.edit', $listing))
            ->assertForbidden()
            ->assertSee(__('app.errors.403.title'));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function nonPublicListings(): array
    {
        return [
            'pending' => ['pending'],
            'rejected' => ['rejected'],
            'suspended owner' => ['suspended owner'],
        ];
    }

    #[DataProvider('nonPublicListings')]
    public function test_a_stranger_gets_the_404_page_on_a_non_public_listing(string $state): void
    {
        $listing = match ($state) {
            'pending' => CarListing::factory()->create(),
            'rejected' => CarListing::factory()->rejected()->create(),
            'suspended owner' => CarListing::factory()->approved()->for(User::factory()->suspended(), 'owner')->create(),
        };

        $this->actingAs(User::factory()->create())->get(route('listings.images.edit', $listing))
            ->assertNotFound()
            ->assertSee(__('app.errors.404.title'));
    }

    /**
     * @return array<string, array{string, ListingStatus}>
     */
    public static function allowedViewers(): array
    {
        return [
            'owner, pending' => ['owner', ListingStatus::Pending],
            'owner, approved' => ['owner', ListingStatus::Approved],
            'owner, rejected' => ['owner', ListingStatus::Rejected],
            'admin, pending' => ['admin', ListingStatus::Pending],
            'admin, approved' => ['admin', ListingStatus::Approved],
        ];
    }

    #[DataProvider('allowedViewers')]
    public function test_the_owner_and_an_admin_reach_the_page(string $viewer, ListingStatus $status): void
    {
        $owner = User::factory()->create();
        $listing = $this->listing($status, $owner);
        $actor = $viewer === 'admin' ? User::factory()->admin()->create() : $owner;

        $this->actingAs($actor)->get(route('listings.images.edit', $listing))
            ->assertOk()
            ->assertViewIs('pages.listings.images')
            ->assertViewHasAll(['listing', 'primaryImage', 'imageCount', 'maxImages', 'canAddImages', 'backUrl']);
    }

    public function test_a_suspended_owner_in_a_live_session_is_signed_out(): void
    {
        $owner = User::factory()->suspended()->create();
        $listing = $this->listing(ListingStatus::Approved, $owner);

        $this->actingAs($owner)->get(route('listings.images.edit', $listing))->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_an_unknown_listing_is_a_404(): void
    {
        $this->actingAs(User::factory()->admin()->create())->get(route('listings.images.edit', 999999))->assertNotFound();
    }

    public function test_the_page_renders_the_back_link_both_segments_and_the_header(): void
    {
        $listing = CarListing::factory()->approved()->withImages(3)->create(['name' => 'Sunset Bel Air']);
        $html = $this->page($listing)->getContent();

        $this->assertMatchesRegularExpression('/<a href="'.preg_quote(route('listings.show', $listing), '/').'" class="inline-flex size-11/', $html);
        $this->assertStringContainsString('<div class="ml-auto flex flex-wrap items-center justify-end gap-2 max-md:basis-full">', $html);
        $this->assertMatchesRegularExpression(
            '/<nav aria-label="Edit listing sections"[^>]*>\s*<a href="'.preg_quote(route('listings.edit', $listing), '/').'"\s+class="[^"]*">Edit Listing<\/a>\s*<a href="'.preg_quote(route('listings.images.edit', $listing), '/').'"\s+aria-current="page"[^>]*>Edit Images<\/a>\s*<\/nav>/',
            $html,
        );
        $this->assertSame(1, substr_count($html, 'aria-current="page"'));
        $this->assertMatchesRegularExpression('/<span[^>]*>Sunset Bel Air · 3 of 8 images<\/span>\s*<h1[^>]*>Edit Car Listing Images<\/h1>/', $html);
    }

    public function test_the_primary_postcard_shows_the_full_variant_with_the_primary_stamp(): void
    {
        $listing = CarListing::factory()->approved()->withImages(2)->create(['name' => 'Sunset Bel Air']);
        $primary = $listing->images()->where('position', 0)->sole();
        $html = $this->page($listing)->getContent();

        $this->assertMatchesRegularExpression(
            '/<img src="'.preg_quote(e($primary->url()), '/').'" alt="Sunset Bel Air — primary image" width="1600" height="900"\s+class="photo-grade h-52.5 w-full object-cover md:h-100">\s*<span[^>]*border-red text-red absolute[^>]*>\s*<span[^>]*><span[^>]*>PRIMARY<\/span>/',
            $html,
        );
    }

    public function test_a_listing_without_images_renders_the_add_card_and_no_postcard_or_items(): void
    {
        $html = $this->page(CarListing::factory()->approved()->create())->getContent();

        $this->assertStringNotContainsString('PRIMARY', $html);
        $this->assertStringNotContainsString('<li class="flex flex-col">', $html);
        $this->assertStringContainsString('type="file"', $html);
    }

    /**
     * @return array<string, array{string, ListingStatus, bool}>
     */
    public static function warningCases(): array
    {
        return [
            'owner, approved' => ['owner', ListingStatus::Approved, true],
            'owner, rejected' => ['owner', ListingStatus::Rejected, true],
            'owner, pending' => ['owner', ListingStatus::Pending, false],
            'admin, approved' => ['admin', ListingStatus::Approved, true],
            'admin, rejected' => ['admin', ListingStatus::Rejected, true],
            'admin, pending' => ['admin', ListingStatus::Pending, false],
        ];
    }

    #[DataProvider('warningCases')]
    public function test_the_reapproval_warning_shows_for_approved_and_rejected_listings_only(string $viewer, ListingStatus $status, bool $shown): void
    {
        $listing = $this->listing($status);
        $actor = $viewer === 'admin' ? User::factory()->admin()->create() : $listing->owner;

        $html = $this->actingAs($actor)->get(route('listings.images.edit', $listing))->getContent();

        $this->assertSame($shown, str_contains($html, 'Saving sends this listing for approval again'));
    }

    public function test_below_the_limit_the_add_card_posts_the_photo_input_with_its_label(): void
    {
        $listing = CarListing::factory()->approved()->withImages(7)->create();
        $html = $this->page($listing)->getContent();

        $this->assertMatchesRegularExpression('/<form method="POST" action="'.preg_quote(route('listings.images.store', $listing), '/').'" enctype="multipart\/form-data"/', $html);
        $this->assertMatchesRegularExpression('/role="group" aria-labelledby="images-label"/', $html);
        $this->assertStringContainsString('<p id="images-label" class="font-heading text-[26px] leading-tight text-red italic">Add new images <span aria-hidden="true">*</span><span class="sr-only">(required)</span></p>', $html);
        $this->assertStringNotContainsString('Car images - max', $html);
        $this->assertSame(1, substr_count($html, '<span aria-hidden="true">*</span>'));
        $this->assertSame(3, substr_count($html, 'name="images[]"'));
        $this->assertMatchesRegularExpression('/<button type="submit"[^>]*border-red text-red[^>]*>Add images<\/button>/', $html);
        $this->assertStringNotContainsString('The limit is 8 images!', $html);
    }

    public function test_at_the_limit_the_add_card_shows_the_message_instead_of_the_form(): void
    {
        $listing = CarListing::factory()->approved()->withImages(8)->create();
        $html = $this->page($listing)->getContent();

        $this->assertMatchesRegularExpression('/<h2[^>]*>Add new images<\/h2>\s*<p[^>]*>\s*<svg[^>]*aria-hidden="true"[^>]*>.*?<\/svg>\s*<span>The limit is 8 images!<\/span>/s', $html);
        $this->assertStringNotContainsString('type="file"', $html);
        $this->assertStringNotContainsString('action="'.route('listings.images.store', $listing).'"', $html);
        $this->assertStringContainsString('· 8 of 8 images', $html);
    }

    public function test_the_gallery_lists_every_image_in_position_order_with_controls_per_item(): void
    {
        $listing = CarListing::factory()->approved()->withImages(3)->create(['name' => 'Sunset Bel Air']);
        $images = $listing->images()->get();
        $html = $this->page($listing)->getContent();

        preg_match_all('/<li class="flex flex-col">(.*?)<\/li>/s', $html, $items);
        $this->assertCount(3, $items[1]);

        foreach ($images as $index => $image) {
            $item = $items[1][$index];
            $number = $index + 1;

            $this->assertStringContainsString('<img src="'.e($image->thumbUrl()).'" alt="Sunset Bel Air — image '.$number.'"', $item);
            $this->assertMatchesRegularExpression('/width="320" height="240" loading="lazy" class="photo-grade h-25 w-full object-cover md:h-45"/', $item);

            if ($index === 0) {
                $this->assertMatchesRegularExpression('/<p class="(?![^"]*border)[^"]*bg-gold\/10[^"]*text-gold-ink[^"]*">\s*Primary image\s*<\/p>/', $item);
                $this->assertStringNotContainsString('<form', $item);
                $this->assertStringNotContainsString('<dialog', $item);

                continue;
            }

            $dialog = 'delete-image-'.$image->id;
            $this->assertMatchesRegularExpression('/<form method="POST" action="'.preg_quote(route('listings.images.primary', [$listing, $image]), '/').'"[^>]*>\s*<input type="hidden" name="_token"[^>]*>\s*<input type="hidden" name="_method" value="PATCH">\s*<button type="submit"[^>]*border-rule[^>]*>Primary\s*<span class="sr-only">image '.$number.'<\/span><\/button>/', $item);
            $this->assertMatchesRegularExpression('/<button type="button"[^>]*border-red-deep[^>]*data-dialog-open="'.$dialog.'" commandfor="'.$dialog.'" command="show-modal">Delete\s*<span class="sr-only">image '.$number.'<\/span><\/button>/', $item);
            $this->assertStringContainsString('<dialog id="'.$dialog.'" aria-labelledby="'.$dialog.'-title"', $item);
            $this->assertStringContainsString('<h2 id="'.$dialog.'-title" class="text-[26px] leading-tight text-red italic">Are you sure you want to delete this image?</h2>', $item);
            $this->assertMatchesRegularExpression('/<form method="POST" action="'.preg_quote(route('listings.images.destroy', [$listing, $image]), '/').'"[^>]*>\s*<input type="hidden" name="_token"[^>]*>\s*<input type="hidden" name="_method" value="DELETE">/', $item);
            $this->assertMatchesRegularExpression('/data-dialog-close="data-dialog-close" commandfor="'.$dialog.'" command="close">Cancel<\/button>\s*<button type="submit"[^>]*border-red-deep[^>]*>Delete\s*<span class="sr-only">image '.$number.'<\/span><\/button>/', $item);
        }
    }

    public function test_the_gallery_has_a_hidden_heading_and_no_control_uses_the_hidden_attribute(): void
    {
        $html = $this->page(CarListing::factory()->approved()->withImages(2)->create())->getContent();

        $this->assertMatchesRegularExpression('/<section aria-labelledby="gallery-heading"[^>]*>\s*<h2 id="gallery-heading" class="sr-only">All images<\/h2>\s*<ul class="grid grid-cols-2 gap-3 md:grid-cols-4 md:gap-7">/', $html);
        $this->assertDoesNotMatchRegularExpression('/<main.*\shidden[\s>=].*<\/main>/s', $html);
    }

    /**
     * @return array<string, array{int}>
     */
    public static function imageCounts(): array
    {
        return ['two images' => [2], 'eight images' => [8]];
    }

    #[DataProvider('imageCounts')]
    public function test_the_page_runs_a_fixed_number_of_queries_whatever_the_image_count(int $count): void
    {
        $listing = CarListing::factory()->approved()->withImages($count)->create();
        $owner = $listing->owner;

        $this->expectsDatabaseQueryCount(3);

        $this->actingAs($owner)->get(route('listings.images.edit', $listing))->assertOk();
    }

    private function page(CarListing $listing): TestResponse
    {
        return $this->actingAs($listing->owner)->get(route('listings.images.edit', $listing))->assertOk();
    }

    private function listing(ListingStatus $status, ?User $owner = null): CarListing
    {
        $factory = match ($status) {
            ListingStatus::Approved => CarListing::factory()->approved(),
            ListingStatus::Rejected => CarListing::factory()->rejected(),
            ListingStatus::Pending => CarListing::factory(),
        };

        return $factory->for($owner ?? User::factory(), 'owner')->create();
    }
}
