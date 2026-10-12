<?php

namespace Tests\Unit;

use App\Models\CarModel;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CarModelTest extends TestCase
{
    /**
     * @return array<string, array{int, bool}>
     */
    public static function yearsAroundProduction(): array
    {
        return [
            'year before the first' => [1954, false],
            'first year' => [1955, true],
            'middle year' => [1956, true],
            'last year' => [1957, true],
            'year after the last' => [1958, false],
        ];
    }

    #[DataProvider('yearsAroundProduction')]
    public function test_covers_year_is_true_only_inside_the_production_years(int $year, bool $expected): void
    {
        $model = new CarModel(['name' => 'Bel Air', 'year_from' => 1955, 'year_to' => 1957]);

        $covers = $model->coversYear($year);

        $this->assertSame($expected, $covers);
    }

    public function test_production_years_joins_the_range_with_an_en_dash(): void
    {
        $model = new CarModel(['name' => 'Bel Air', 'year_from' => 1955, 'year_to' => 1957]);

        $label = $model->productionYears();

        $this->assertSame("1955\u{2013}1957", $label);
    }

    public function test_production_years_repeats_a_single_year(): void
    {
        $model = new CarModel(['name' => 'Superbird', 'year_from' => 1960, 'year_to' => 1960]);

        $label = $model->productionYears();

        $this->assertSame("1960\u{2013}1960", $label);
    }
}
