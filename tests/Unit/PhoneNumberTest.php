<?php

namespace Tests\Unit;

use App\Support\PhoneNumber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PhoneNumberTest extends TestCase
{
    /**
     * @return array<string, array{string, string}>
     */
    public static function examples(): array
    {
        return [
            'formatted with a plus' => ['+1 (555) 123-45', '+155512345'],
            'plus in the middle' => ['12+34', '1234'],
            'double plus' => ['++12', '+12'],
            'leading whitespace before the plus' => [' +385 91', '+38591'],
            'source format' => ['123/456-789', '123456789'],
        ];
    }

    #[DataProvider('examples')]
    public function test_it_keeps_digits_and_one_leading_plus(string $input, string $expected): void
    {
        $this->assertSame($expected, PhoneNumber::normalize($input));
    }
}
