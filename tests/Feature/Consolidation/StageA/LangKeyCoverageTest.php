<?php

namespace Tests\Feature\Consolidation\StageA;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Lang;
use Tests\TestCase;

class LangKeyCoverageTest extends TestCase
{
    private const string KEY_PATTERN = '/(?:__|@lang|trans)\(\s*([\'"])([^\'"]+)\1/';

    private const string APP_KEY_LITERAL_PATTERN = '/(?<!config\()([\'"])(app\.[a-z0-9_]+(?:\.[a-z0-9_]+)+)\1/';

    public function test_every_literal_translation_key_exists_in_lang_en(): void
    {
        ['literal' => $keys] = $this->scan();
        $missing = array_values(array_filter(array_keys($keys), fn (string $key): bool => ! Lang::has($key, 'en')));

        $this->assertArrayHasKey('app.errors.403.title', $keys);
        $this->assertArrayHasKey('app.errors.session_expired', $keys);
        $this->assertArrayHasKey('app.nav.home', $keys);
        $this->assertArrayHasKey('app.flash.success_kicker', $keys);
        $this->assertSame([], array_map(fn (string $key): string => "{$key} ({$keys[$key]})", $missing));
    }

    public function test_interpolated_keys_are_out_of_scope_and_left_to_their_own_tests(): void
    {
        ['dynamic' => $dynamic] = $this->scan();

        $this->assertArrayHasKey('app.profile.details.{$key}', $dynamic);
    }

    /**
     * @return array{literal: array<string, string>, dynamic: array<string, string>}
     */
    private function scan(): array
    {
        $found = ['literal' => [], 'dynamic' => []];

        foreach ([resource_path('views'), app_path(), base_path('bootstrap')] as $directory) {
            foreach (File::allFiles($directory) as $file) {
                if (str_starts_with($file->getRelativePathname(), 'cache')) {
                    continue;
                }

                preg_match_all(self::KEY_PATTERN, $file->getContents(), $calls);
                preg_match_all(self::APP_KEY_LITERAL_PATTERN, $file->getContents(), $literals);

                foreach ([...$calls[2], ...$literals[2]] as $key) {
                    $kind = str_contains($key, '$') || str_contains($key, '{') ? 'dynamic' : 'literal';
                    $found[$kind][$key] ??= $file->getRelativePathname();
                }
            }
        }

        return $found;
    }
}
