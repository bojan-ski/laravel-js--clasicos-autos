<?php

namespace App\View\Components\Listings;

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
use App\Support\EnumOptions;
use BackedEnum;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\View\Component;

class AdvancedSearchForm extends Component
{
    /**
     * @param  array<string, string|int|BackedEnum|null>  $filters
     * @param  Collection<int, CarMaker>  $makers
     */
    public function __construct(
        public array $filters,
        public Collection $makers,
        public int $total,
    ) {}

    /**
     * @return list<array{title: string, note: string, fields: list<array{name: string, label: string, placeholder: string, type: string, value: string, options: array<string, string>, min: int|null, max: int|null, maxlength: int|null, inputmode: string|null}>}>
     */
    public function fieldsets(): array
    {
        $minYear = config('listings.min_year');
        $maxYear = config('listings.max_year');

        return [
            [
                'title' => __('app.listings.advanced.fieldsets.car.title'),
                'note' => __('app.listings.advanced.fieldsets.car.note'),
                'fields' => [
                    $this->select('car_maker', __('app.listings.advanced.fields.car_maker.label'), __('app.listings.advanced.fields.car_maker.placeholder'), $this->makerOptions()),
                    $this->text('model', __('app.listings.advanced.fields.model.label'), __('app.listings.advanced.fields.model.placeholder'), 255),
                    $this->number('year_from', __('app.listings.advanced.fields.year_from.label'), __('app.listings.advanced.fields.year_from.placeholder'), $minYear, $maxYear),
                    $this->number('year_to', __('app.listings.advanced.fields.year_to.label'), __('app.listings.advanced.fields.year_to.placeholder'), $minYear, $maxYear),
                    $this->number('mileage', __('app.listings.advanced.fields.mileage.label'), __('app.listings.advanced.fields.mileage.placeholder'), 0),
                    $this->select('transmission', __('app.listings.advanced.fields.transmission.label'), __('app.listings.advanced.fields.transmission.placeholder'), EnumOptions::for(Transmission::class)),
                    $this->select('fuel_type', __('app.listings.advanced.fields.fuel_type.label'), __('app.listings.advanced.fields.fuel_type.placeholder'), EnumOptions::for(FuelType::class)),
                    $this->select('body_type', __('app.listings.advanced.fields.body_type.label'), __('app.listings.advanced.fields.body_type.placeholder'), EnumOptions::for(BodyType::class)),
                ],
            ],
            [
                'title' => __('app.listings.advanced.fieldsets.engine.title'),
                'note' => __('app.listings.advanced.fieldsets.engine.note'),
                'fields' => [
                    $this->text('engine_size', __('app.listings.advanced.fields.engine_size.label'), __('app.listings.advanced.fields.engine_size.placeholder'), 50),
                    $this->text('engine_type', __('app.listings.advanced.fields.engine_type.label'), __('app.listings.advanced.fields.engine_type.placeholder'), 100),
                    $this->select('engine_history', __('app.listings.advanced.fields.engine_history.label'), __('app.listings.advanced.fields.engine_history.placeholder'), EnumOptions::for(EngineHistory::class)),
                    $this->select('engine_condition', __('app.listings.advanced.fields.engine_condition.label'), __('app.listings.advanced.fields.engine_condition.placeholder'), EnumOptions::for(EngineCondition::class)),
                ],
            ],
            [
                'title' => __('app.listings.advanced.fieldsets.history.title'),
                'note' => __('app.listings.advanced.fieldsets.history.note'),
                'fields' => [
                    $this->select('restoration_history', __('app.listings.advanced.fields.restoration_history.label'), __('app.listings.advanced.fields.restoration_history.placeholder'), EnumOptions::for(RestorationHistory::class)),
                    $this->select('odometer', __('app.listings.advanced.fields.odometer.label'), __('app.listings.advanced.fields.odometer.placeholder'), EnumOptions::for(Odometer::class)),
                    $this->select('license_plate_type', __('app.listings.advanced.fields.license_plate_type.label'), __('app.listings.advanced.fields.license_plate_type.placeholder'), EnumOptions::for(LicensePlateType::class)),
                    $this->select('documentation_status', __('app.listings.advanced.fields.documentation_status.label'), __('app.listings.advanced.fields.documentation_status.placeholder'), EnumOptions::for(DocumentationStatus::class)),
                ],
            ],
            [
                'title' => __('app.listings.advanced.fieldsets.looks.title'),
                'note' => __('app.listings.advanced.fieldsets.looks.note'),
                'fields' => [
                    $this->text('exterior_color', __('app.listings.advanced.fields.exterior_color.label'), __('app.listings.advanced.fields.exterior_color.placeholder'), 50),
                    $this->select('exterior_condition', __('app.listings.advanced.fields.exterior_condition.label'), __('app.listings.advanced.fields.exterior_condition.placeholder'), EnumOptions::for(VehicleCondition::class)),
                    $this->text('interior_color', __('app.listings.advanced.fields.interior_color.label'), __('app.listings.advanced.fields.interior_color.placeholder'), 50),
                    $this->select('interior_condition', __('app.listings.advanced.fields.interior_condition.label'), __('app.listings.advanced.fields.interior_condition.placeholder'), EnumOptions::for(VehicleCondition::class)),
                    $this->select('seat_material', __('app.listings.advanced.fields.seat_material.label'), __('app.listings.advanced.fields.seat_material.placeholder'), EnumOptions::for(SeatMaterial::class)),
                ],
            ],
            [
                'title' => __('app.listings.advanced.fieldsets.location.title'),
                'note' => __('app.listings.advanced.fieldsets.location.note'),
                'fields' => [
                    $this->text('city', __('app.listings.advanced.fields.city.label'), __('app.listings.advanced.fields.city.placeholder'), 100),
                    $this->text('state', __('app.listings.advanced.fields.state.label'), __('app.listings.advanced.fields.state.placeholder'), 100),
                    $this->text('zipcode', __('app.listings.advanced.fields.zipcode.label'), __('app.listings.advanced.fields.zipcode.placeholder'), 20, 'numeric'),
                    $this->number('price_from', __('app.listings.advanced.fields.price_from.label'), __('app.listings.advanced.fields.price_from.placeholder'), 0),
                    $this->number('price_to', __('app.listings.advanced.fields.price_to.label'), __('app.listings.advanced.fields.price_to.placeholder'), 0),
                ],
            ],
        ];
    }

    public function render(): View
    {
        return view('components.listings.advanced-search-form');
    }

    /**
     * @return array<string, string>
     */
    private function makerOptions(): array
    {
        return $this->makers->mapWithKeys(fn (CarMaker $maker): array => [(string) $maker->id => $maker->name])->all();
    }

    /**
     * @return array{name: string, label: string, placeholder: string, type: string, value: string, options: array<string, string>, min: int|null, max: int|null, maxlength: int|null, inputmode: string|null}
     */
    private function text(string $name, string $label, string $placeholder, int $maxlength, ?string $inputmode = null): array
    {
        return $this->field($name, $label, $placeholder, 'text', maxlength: $maxlength, inputmode: $inputmode);
    }

    /**
     * @return array{name: string, label: string, placeholder: string, type: string, value: string, options: array<string, string>, min: int|null, max: int|null, maxlength: int|null, inputmode: string|null}
     */
    private function number(string $name, string $label, string $placeholder, int $min, ?int $max = null): array
    {
        return $this->field($name, $label, $placeholder, 'number', min: $min, max: $max);
    }

    /**
     * @param  array<string, string>  $options
     * @return array{name: string, label: string, placeholder: string, type: string, value: string, options: array<string, string>, min: int|null, max: int|null, maxlength: int|null, inputmode: string|null}
     */
    private function select(string $name, string $label, string $placeholder, array $options): array
    {
        return $this->field($name, $label, $placeholder, 'select', options: $options);
    }

    /**
     * @param  array<string, string>  $options
     * @return array{name: string, label: string, placeholder: string, type: string, value: string, options: array<string, string>, min: int|null, max: int|null, maxlength: int|null, inputmode: string|null}
     */
    private function field(
        string $name,
        string $label,
        string $placeholder,
        string $type,
        array $options = [],
        ?int $min = null,
        ?int $max = null,
        ?int $maxlength = null,
        ?string $inputmode = null,
    ): array {
        $value = $this->filters[$name] ?? null;

        return [
            'name' => $name,
            'label' => $label,
            'placeholder' => $placeholder,
            'type' => $type,
            'value' => $value instanceof BackedEnum ? (string) $value->value : (string) $value,
            'options' => $options,
            'min' => $min,
            'max' => $max,
            'maxlength' => $maxlength,
            'inputmode' => $inputmode,
        ];
    }
}
