<?php

namespace Tests\Feature\Listings;

use App\Contracts\ListingOption;
use App\Enums\BodyType;
use App\Enums\DocumentationStatus;
use App\Enums\EngineCondition;
use App\Enums\EngineHistory;
use App\Enums\FuelType;
use App\Enums\LicensePlateType;
use App\Enums\ListingStatus;
use App\Enums\Odometer;
use App\Enums\RestorationHistory;
use App\Enums\SeatMaterial;
use App\Enums\Transmission;
use App\Enums\VehicleCondition;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ListingOptionsTest extends TestCase
{
    /**
     * @return array<string, array{class-string<ListingOption&\BackedEnum>, list<string>}>
     */
    public static function optionEnums(): array
    {
        return [
            'transmission' => [Transmission::class, ['Manual', 'Automatic']],
            'fuel type' => [FuelType::class, ['Gasoline', 'Diesel']],
            'engine history' => [EngineHistory::class, ['Original Engine', 'Replaced Engine', 'Rebuilt Engine']],
            'engine condition' => [EngineCondition::class, ['Running', 'Needs Tuning', 'Not Running', 'Rebuilt Engine', 'Original Factory Engine']],
            'restoration history' => [RestorationHistory::class, ['Fully Restored', 'Partially Restored', 'Barn Find', 'Unrestored Original']],
            'body type' => [BodyType::class, ['Coupe', 'Sedan', 'Convertible', 'Wagon']],
            'seat material' => [SeatMaterial::class, ['Leather', 'Cloth', 'Vinyl', 'Velour']],
            'odometer' => [Odometer::class, ['Original', 'Rebuilt', 'Unknown']],
            'license plate type' => [LicensePlateType::class, ['Original Plate', 'Modern Plate', 'No Plate']],
            'documentation status' => [DocumentationStatus::class, ['Original Papers', 'Missing Papers', 'Custom Registration']],
            'vehicle condition' => [VehicleCondition::class, ['Showroom Condition', 'Good', 'Fair', 'Needs Restoration']],
        ];
    }

    /**
     * @param  class-string<ListingOption&\BackedEnum>  $enum
     * @param  list<string>  $values
     */
    #[DataProvider('optionEnums')]
    public function test_option_values_are_the_stored_strings(string $enum, array $values): void
    {
        $actual = array_map(fn (\BackedEnum $case) => $case->value, $enum::cases());

        $this->assertSame($values, $actual);
    }

    /**
     * @param  class-string<ListingOption&\BackedEnum>  $enum
     * @param  list<string>  $values
     */
    #[DataProvider('optionEnums')]
    public function test_option_labels_come_from_lang_and_equal_the_values(string $enum, array $values): void
    {
        $labels = array_map(fn (ListingOption $case) => $case->label(), $enum::cases());

        $this->assertSame($values, $labels);
        $this->assertContainsOnlyInstancesOf(ListingOption::class, $enum::cases());
    }

    public function test_status_values_and_labels(): void
    {
        $labels = collect(ListingStatus::cases())->mapWithKeys(fn (ListingStatus $status) => [$status->value => $status->label()])->all();

        $this->assertSame(['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'], $labels);
        $this->assertNotInstanceOf(ListingOption::class, ListingStatus::Pending);
    }
}
