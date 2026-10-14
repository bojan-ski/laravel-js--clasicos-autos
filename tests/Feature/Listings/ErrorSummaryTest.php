<?php

namespace Tests\Feature\Listings;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ErrorSummaryTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_nothing_renders_without_errors(): void
    {
        $this->withViewErrors([]);

        $this->assertSame('', trim((string) $this->blade('<x-forms.error-summary />')));
    }

    public function test_every_message_links_to_its_field(): void
    {
        $this->withViewErrors(['name' => 'Name is wrong.', 'price' => ['Price is low.', 'Price is odd.']]);

        $html = (string) $this->blade('<x-forms.error-summary />');

        preg_match_all('/<li><a href="#([a-z_]+)"[^>]*>([^<]+)<\/a><\/li>/', $html, $links);
        $this->assertSame(['name', 'price', 'price'], $links[1]);
        $this->assertSame(['Name is wrong.', 'Price is low.', 'Price is odd.'], $links[2]);
    }

    public function test_photo_errors_link_to_the_photo_group(): void
    {
        $this->withViewErrors(['images' => 'Add a photo.', 'images.0' => 'The first photo is too large.']);

        $html = (string) $this->blade('<x-forms.error-summary />');

        $this->assertSame(2, substr_count($html, '<a href="#images"'));
        $this->assertStringNotContainsString('#images.0', $html);
    }

    public function test_the_summary_is_a_focus_target_named_by_its_counted_lead_in(): void
    {
        $this->withViewErrors(['name' => 'Name is wrong.', 'price' => ['Price is low.', 'Price is odd.']]);

        $html = (string) $this->blade('<x-forms.error-summary />');

        $this->assertMatchesRegularExpression('/<div data-error-summary tabindex="-1" role="alert" aria-labelledby="error-summary-title"/', $html);
        $this->assertStringContainsString('<p id="error-summary-title" class="font-semibold">'.trans_choice('app.forms.error_summary', 3, ['count' => 3]).'</p>', $html);
        $this->assertStringContainsString('There are 3 problems with this form', $html);
    }

    public function test_a_single_error_reads_in_the_singular(): void
    {
        $this->withViewErrors(['name' => 'Name is wrong.']);

        $this->assertStringContainsString('There is 1 problem with this form', (string) $this->blade('<x-forms.error-summary />'));
    }

    public function test_the_photo_group_is_a_focusable_link_target(): void
    {
        $this->withViewErrors([]);

        $this->assertMatchesRegularExpression('/<div id="images" tabindex="-1" data-photo-input/', (string) $this->blade('<x-listings.photo-input />'));
    }

    public function test_every_summary_link_on_the_create_page_targets_an_element_on_that_page(): void
    {
        $this->withoutVite();
        Storage::fake('public');

        $html = $this->actingAs(User::factory()->create())->followingRedirects()
            ->post(route('listings.store'), [])
            ->assertOk()
            ->getContent();

        preg_match('/data-error-summary.*?<\/ul>/s', $html, $summary);
        preg_match_all('/href="#([^"]+)"/', $summary[0], $targets);

        $this->assertContains('images', $targets[1]);
        $this->assertContains('name', $targets[1]);

        foreach (array_unique($targets[1]) as $id) {
            $this->assertSame(1, substr_count($html, 'id="'.$id.'"'), "#{$id} must exist once on the page");
        }
    }
}
