<?php

namespace Tests\Feature\Support;

use App\Enums\BodyType;
use App\Enums\DocumentationStatus;
use App\Enums\EngineCondition;
use App\Enums\EngineHistory;
use App\Enums\FuelType;
use App\Enums\LicensePlateType;
use App\Enums\Odometer;
use App\Enums\RestorationHistory;
use App\Enums\SeatMaterial;
use App\Enums\Transmission;
use App\Enums\VehicleCondition;
use App\Models\CarListing;
use App\Models\CarMaker;
use App\Models\CarModel;
use App\Support\ComparisonRows;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ComparisonRowsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_the_rows_are_the_24_source_labels_in_source_order(): void
    {
        $rows = (new ComparisonRows($this->fullListing()))->rows();

        $this->assertSame([
            'Model', 'Car Maker', 'Year', 'Mileage', 'Exterior Color', 'Interior Color',
            'Transmission', 'Fuel Type', 'Engine Size', 'Engine Type', 'Odometer', 'Exterior Condition',
            'Interior Condition', 'Seat Material', 'Engine History', 'Engine Condition', 'Price', 'Location City',
            'Location State', 'Body Type', 'Restoration History', 'Original Parts (%)', 'License Plate Type', 'Documentation',
        ], array_column($rows, 'label'));
    }

    public function test_a_full_listing_renders_every_display_string(): void
    {
        $rows = (new ComparisonRows($this->fullListing()))->rows();

        $this->assertSame([
            'Bel Air', 'Chevrolet', '1957', '45,000 km', 'Turquoise', 'Ivory',
            'Manual', 'Gasoline', '4.6L', 'V8', 'Original', 'Good',
            'Fair', 'Vinyl', 'Rebuilt Engine', 'Running', '$24,500', 'Havana',
            'La Habana', 'Coupe', 'Fully Restored', '85', 'Original Plate', 'Original Papers',
        ], array_column($rows, 'first'));
    }

    public function test_optional_values_left_empty_render_a_hyphen(): void
    {
        $rows = (new ComparisonRows($this->bareListing()))->rows();

        $values = array_combine(array_column($rows, 'label'), array_column($rows, 'first'));
        $this->assertSame([
            'Exterior Color' => '-',
            'Interior Color' => '-',
            'Engine Size' => '-',
            'Engine Type' => '-',
            'Exterior Condition' => '-',
            'Interior Condition' => '-',
            'Seat Material' => '-',
            'Body Type' => '-',
            'Original Parts (%)' => '-',
            'License Plate Type' => '-',
        ], array_filter($values, fn (string $value): bool => $value === '-'));
    }

    public function test_zero_original_parts_renders_zero_not_a_hyphen(): void
    {
        $rows = (new ComparisonRows($this->bareListing(['original_parts_percentage' => 0])))->rows();

        $this->assertSame('0', $rows[21]['first']);
    }

    public function test_a_single_listing_never_differs_and_its_second_column_is_a_hyphen(): void
    {
        $rows = (new ComparisonRows($this->fullListing()))->rows();

        $this->assertSame(array_fill(0, 24, '-'), array_column($rows, 'second'));
        $this->assertSame(array_fill(0, 24, false), array_column($rows, 'differs'));
    }

    public function test_a_row_differs_exactly_when_its_two_display_strings_differ(): void
    {
        $first = $this->fullListing();
        $second = $this->bareListing(['year' => 1957, 'price' => 24500]);

        $rows = (new ComparisonRows($first, $second))->rows();

        $differs = array_combine(array_column($rows, 'label'), array_column($rows, 'differs'));
        $this->assertFalse($differs['Year']);
        $this->assertFalse($differs['Price']);
        $this->assertTrue($differs['Car Maker']);
        $this->assertTrue($differs['Exterior Color']);
        $this->assertSame('-', $rows[4]['second']);
    }

    public function test_two_empty_values_do_not_differ(): void
    {
        $rows = (new ComparisonRows($this->bareListing(), $this->bareListing()))->rows();

        $this->assertFalse($rows[4]['differs']);
        $this->assertFalse($rows[22]['differs']);
    }

    private function fullListing(): CarListing
    {
        $maker = CarMaker::factory()->create(['name' => 'Chevrolet']);
        $carModel = CarModel::factory()->for($maker, 'maker')->create(['name' => 'Bel Air', 'year_from' => 1950, 'year_to' => 1981]);

        return CarListing::factory()->forCarModel($carModel)->create([
            'year' => 1957,
            'mileage' => 45000,
            'exterior_color' => 'turquoise',
            'interior_color' => 'ivory',
            'transmission' => Transmission::Manual,
            'fuel_type' => FuelType::Gasoline,
            'engine_size' => '4.6L',
            'engine_type' => 'V8',
            'odometer' => Odometer::Original,
            'exterior_condition' => VehicleCondition::Good,
            'interior_condition' => VehicleCondition::Fair,
            'seat_material' => SeatMaterial::Vinyl,
            'engine_history' => EngineHistory::RebuiltEngine,
            'engine_condition' => EngineCondition::Running,
            'price' => 24500,
            'location_city' => 'Havana',
            'location_state' => 'La Habana',
            'body_type' => BodyType::Coupe,
            'restoration_history' => RestorationHistory::FullyRestored,
            'original_parts_percentage' => 85,
            'license_plate_type' => LicensePlateType::OriginalPlate,
            'documentation_status' => DocumentationStatus::OriginalPapers,
        ])->load('maker');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function bareListing(array $attributes = []): CarListing
    {
        return CarListing::factory()->create($attributes)->load('maker');
    }
}
