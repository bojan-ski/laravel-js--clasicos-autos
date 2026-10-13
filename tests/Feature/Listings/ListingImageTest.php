<?php

namespace Tests\Feature\Listings;

use App\Models\CarListing;
use App\Models\ListingImage;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ListingImageTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_each_variant_url_points_at_the_public_disk(): void
    {
        config(['filesystems.disks.public.url' => 'https://clasicos.test/storage']);
        $image = new ListingImage([
            'path' => 'listings/5/abc.webp',
            'card_path' => 'listings/5/card/abc.webp',
            'thumb_path' => 'listings/5/thumb/abc.webp',
        ]);

        $urls = [$image->url(), $image->cardUrl(), $image->thumbUrl()];

        $this->assertSame([
            'https://clasicos.test/storage/listings/5/abc.webp',
            'https://clasicos.test/storage/listings/5/card/abc.webp',
            'https://clasicos.test/storage/listings/5/thumb/abc.webp',
        ], $urls);
    }

    public function test_the_primary_image_is_the_lowest_position(): void
    {
        $listing = CarListing::factory()->create();
        ListingImage::factory()->for($listing, 'listing')->create(['position' => 1]);
        $first = ListingImage::factory()->for($listing, 'listing')->create(['position' => 0]);

        $primary = $listing->primaryImage()->sole();

        $this->assertTrue($primary->is($first));
    }

    public function test_images_are_ordered_by_position(): void
    {
        $listing = CarListing::factory()->create();
        ListingImage::factory()->for($listing, 'listing')->create(['position' => 2]);
        ListingImage::factory()->for($listing, 'listing')->create(['position' => 0]);
        ListingImage::factory()->for($listing, 'listing')->create(['position' => 1]);

        $positions = $listing->images()->pluck('position')->all();

        $this->assertSame([0, 1, 2], $positions);
    }
}
