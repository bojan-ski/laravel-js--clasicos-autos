<?php

namespace Tests\Unit\Seo;

use App\Support\Seo\MetaDescription;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MetaDescriptionTest extends TestCase
{
    /**
     * @return array<string, array{?string}>
     */
    public static function emptyTexts(): array
    {
        return [
            'null' => [null],
            'empty' => [''],
            'whitespace only' => ["  \n\t "],
            'tags only' => ['<p> </p>'],
        ];
    }

    #[DataProvider('emptyTexts')]
    public function test_empty_text_gives_no_excerpt(?string $text): void
    {
        $this->assertNull(MetaDescription::excerpt($text));
    }

    public function test_tags_are_stripped_and_whitespace_collapsed(): void
    {
        $this->assertSame('A restored Bel Air in two-tone paint.', MetaDescription::excerpt("  <p>A restored\n\nBel Air</p>   in <b>two-tone</b> paint.  "));
    }

    public function test_text_at_the_limit_is_kept_whole(): void
    {
        $text = str_repeat('a', 160);

        $this->assertSame($text, MetaDescription::excerpt($text));
    }

    public function test_long_text_is_cut_at_the_last_space_and_ends_with_an_ellipsis(): void
    {
        $text = str_repeat('word ', 40);

        $excerpt = MetaDescription::excerpt($text);

        $this->assertLessThanOrEqual(160, mb_strlen($excerpt));
        $this->assertStringEndsWith('word…', $excerpt);
        $this->assertSame(trim(str_repeat('word ', 31)).'…', $excerpt);
    }

    public function test_multibyte_text_is_measured_in_characters(): void
    {
        $text = str_repeat('Clásicos ', 20);

        $excerpt = MetaDescription::excerpt($text);

        $this->assertLessThanOrEqual(160, mb_strlen($excerpt));
        $this->assertStringEndsWith('Clásicos…', $excerpt);
    }

    public function test_a_custom_limit_is_respected(): void
    {
        $this->assertSame('one two…', MetaDescription::excerpt('one two three', 10));
    }
}
