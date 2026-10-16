<?php

namespace App\Support\Seo;

final class MetaDescription
{
    public static function excerpt(?string $text, int $max = 160): ?string
    {
        $plain = trim((string) preg_replace('/\s+/u', ' ', strip_tags((string) $text)));

        if ($plain === '') {
            return null;
        }

        if (mb_strlen($plain) <= $max) {
            return $plain;
        }

        $cut = mb_substr($plain, 0, $max - 1);
        $lastSpace = mb_strrpos($cut, ' ');

        if ($lastSpace !== false && $lastSpace > 0) {
            $cut = mb_substr($cut, 0, $lastSpace);
        }

        return rtrim($cut, ' ,;:').'…';
    }
}
