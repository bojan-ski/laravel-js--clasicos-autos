<?php

namespace App\Support;

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
use App\Models\CarMaker;
use Illuminate\Support\Collection;

final readonly class ListingFormOptions
{
    public const string OTHER_MODEL = 'other';

    public const array ENUM_FIELDS = [
        'transmission' => Transmission::class,
        'fuel_type' => FuelType::class,
        'engine_history' => EngineHistory::class,
        'engine_condition' => EngineCondition::class,
        'restoration_history' => RestorationHistory::class,
        'odometer' => Odometer::class,
        'license_plate_type' => LicensePlateType::class,
        'documentation_status' => DocumentationStatus::class,
        'body_type' => BodyType::class,
        'exterior_condition' => VehicleCondition::class,
        'interior_condition' => VehicleCondition::class,
        'seat_material' => SeatMaterial::class,
    ];

    /**
     * @param  array<int, string>  $makers
     * @param  array<string, string|array<int, string>>  $models
     * @param  array{other: string, models: array<int, list<array{id: int, name: string}>>}  $modelConfig
     * @param  array<string, array<string, string>>  $enums
     */
    public function __construct(
        public array $makers,
        public array $models,
        public array $modelConfig,
        public array $enums,
    ) {}

    /**
     * @param  Collection<int, CarMaker>  $makers
     */
    public static function fromMakers(Collection $makers): self
    {
        $makerOptions = [];
        $modelGroups = [];
        $configModels = [];

        foreach ($makers as $maker) {
            $makerOptions[$maker->id] = $maker->name;
            $configModels[$maker->id] = [];

            foreach ($maker->activeModels as $model) {
                $modelGroups[$maker->name][$model->id] = $model->name;
                $configModels[$maker->id][] = ['id' => $model->id, 'name' => $model->name];
            }
        }

        $modelGroups[self::OTHER_MODEL] = __('app.listings.form.other_model');

        $enums = [];

        foreach (self::ENUM_FIELDS as $field => $enum) {
            $enums[$field] = EnumOptions::for($enum);
        }

        return new self(
            makers: $makerOptions,
            models: $modelGroups,
            modelConfig: ['other' => self::OTHER_MODEL, 'models' => $configModels],
            enums: $enums,
        );
    }
}
