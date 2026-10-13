<?php

namespace Tests\Feature\Listings;

use Illuminate\Pagination\LengthAwarePaginator;
use Tests\TestCase;

class PaginationViewTest extends TestCase
{
    public function test_a_single_page_renders_nothing(): void
    {
        $this->assertSame('', trim($this->links(total: 5, currentPage: 1)));
    }

    public function test_the_postal_view_replaces_the_framework_view(): void
    {
        $html = $this->links(total: 36, currentPage: 2);

        $this->assertStringContainsString('aria-label="'.__('app.pagination.label').'"', $html);
        $this->assertStringNotContainsString('Showing', $html);
    }

    public function test_the_current_page_box_is_marked_current(): void
    {
        $html = $this->links(total: 36, currentPage: 2);

        $this->assertSame(1, substr_count($html, 'aria-current="page"'));
        $this->assertMatchesRegularExpression('/aria-current="page"[^>]*>2</', $html);
    }

    public function test_the_mobile_line_shows_the_page_of_the_last_page(): void
    {
        $this->assertStringContainsString(
            __('app.pagination.page_of', ['current' => 2, 'last' => 3]),
            $this->links(total: 36, currentPage: 2),
        );
    }

    public function test_the_previous_controls_are_inert_on_the_first_page(): void
    {
        $html = $this->links(total: 36, currentPage: 1);

        $this->assertStringNotContainsString('rel="prev"', $html);
        $this->assertStringContainsString('rel="next"', $html);
        $this->assertSame(2, substr_count($html, 'aria-disabled="true"'));
    }

    public function test_the_next_controls_are_inert_on_the_last_page(): void
    {
        $html = $this->links(total: 36, currentPage: 3);

        $this->assertStringNotContainsString('rel="next"', $html);
        $this->assertStringContainsString('rel="prev"', $html);
        $this->assertSame(2, substr_count($html, 'aria-disabled="true"'));
    }

    public function test_distant_pages_collapse_into_a_gap(): void
    {
        $this->assertStringContainsString('…', $this->links(total: 240, currentPage: 10));
    }

    private function links(int $total, int $currentPage): string
    {
        $paginator = new LengthAwarePaginator([], $total, 12, $currentPage, ['path' => '/listings']);

        return $paginator->links()->toHtml();
    }
}
