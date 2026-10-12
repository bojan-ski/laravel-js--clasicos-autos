<?php

namespace App\Support\Legal;

final readonly class LegalDocument
{
    /**
     * @param  list<string>  $disclaimer
     * @param  list<LegalSection>  $sections
     */
    public function __construct(
        public string $kicker,
        public string $title,
        public array $disclaimer,
        public string $intro,
        public array $sections,
    ) {}

    public static function fromLang(string $key): self
    {
        /** @var array{title: string, intro: string, sections: array<string, array{heading: string, items?: list<string>, paragraph?: string}>} $document */
        $document = __("app.legal.{$key}");

        $sections = [];

        foreach ($document['sections'] as $anchor => $section) {
            $sections[] = new LegalSection(
                anchor: $anchor,
                heading: $section['heading'],
                items: $section['items'] ?? null,
                paragraph: $section['paragraph'] ?? null,
            );
        }

        return new self(
            kicker: __('app.legal.kicker'),
            title: $document['title'],
            disclaimer: __('app.legal.disclaimer'),
            intro: $document['intro'],
            sections: $sections,
        );
    }
}
