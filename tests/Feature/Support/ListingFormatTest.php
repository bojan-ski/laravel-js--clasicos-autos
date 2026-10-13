<?php

namespace Tests\Feature\Support;

use App\Support\ListingFormat;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class ListingFormatTest extends TestCase
{
    #[TestWith([0, '$0'])]
    #[TestWith([24500, '$24,500'])]
    #[TestWith([1250000, '$1,250,000'])]
    public function test_a_price_is_whole_dollars_with_comma_thousands(int $price, string $expected): void
    {
        $this->assertSame($expected, ListingFormat::price($price));
    }

    #[TestWith([950, '950 km'])]
    #[TestWith([123456, '123,456 km'])]
    public function test_a_mileage_has_comma_thousands_and_the_km_unit(int $mileage, string $expected): void
    {
        $this->assertSame($expected, ListingFormat::mileage($mileage));
    }

    public function test_a_date_is_day_month_name_year_with_a_leading_zero(): void
    {
        $this->assertSame('05 March 2026', ListingFormat::date(Carbon::create(2026, 3, 5, 14, 30)));
    }
}
