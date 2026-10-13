<?php

namespace App\Http\Requests\Listings;

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
use BackedEnum;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\Attributes\RedirectToRoute;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

#[RedirectToRoute('listings.advanced')]
class AdvancedSearchRequest extends FormRequest
{
    private const array TEXT_FIELDS = ['model', 'engine_size', 'engine_type', 'exterior_color', 'interior_color', 'city', 'state', 'zipcode'];

    private const array INTEGER_FIELDS = ['car_maker', 'year_from', 'year_to', 'mileage', 'price_from', 'price_to'];

    private const array ENUM_FIELDS = [
        'transmission' => Transmission::class,
        'fuel_type' => FuelType::class,
        'body_type' => BodyType::class,
        'engine_history' => EngineHistory::class,
        'engine_condition' => EngineCondition::class,
        'restoration_history' => RestorationHistory::class,
        'odometer' => Odometer::class,
        'license_plate_type' => LicensePlateType::class,
        'documentation_status' => DocumentationStatus::class,
        'exterior_condition' => VehicleCondition::class,
        'interior_condition' => VehicleCondition::class,
        'seat_material' => SeatMaterial::class,
    ];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $years = 'between:'.config('listings.min_year').','.config('listings.max_year');

        $rules = [
            'car_maker' => ['nullable', 'integer', Rule::exists('car_makers', 'id')->where('is_active', true)],
            'model' => ['nullable', 'string', 'max:255'],
            'year_from' => ['nullable', 'integer', $years],
            'year_to' => ['nullable', 'integer', $years],
            'mileage' => ['nullable', 'integer', 'min:0'],
            'engine_size' => ['nullable', 'string', 'max:50'],
            'engine_type' => ['nullable', 'string', 'max:100'],
            'exterior_color' => ['nullable', 'string', 'max:50'],
            'interior_color' => ['nullable', 'string', 'max:50'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'zipcode' => ['nullable', 'string', 'max:20'],
            'price_from' => ['nullable', 'integer', 'min:0'],
            'price_to' => ['nullable', 'integer', 'min:0'],
        ];

        foreach (self::ENUM_FIELDS as $field => $enum) {
            $rules[$field] = ['nullable', Rule::enum($enum)];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'car_maker' => __('app.listings.advanced.fields.car_maker.label'),
            'model' => __('app.listings.advanced.fields.model.label'),
            'year_from' => __('app.listings.advanced.fields.year_from.label'),
            'year_to' => __('app.listings.advanced.fields.year_to.label'),
            'mileage' => __('app.listings.advanced.fields.mileage.label'),
            'transmission' => __('app.listings.advanced.fields.transmission.label'),
            'fuel_type' => __('app.listings.advanced.fields.fuel_type.label'),
            'body_type' => __('app.listings.advanced.fields.body_type.label'),
            'engine_size' => __('app.listings.advanced.fields.engine_size.label'),
            'engine_type' => __('app.listings.advanced.fields.engine_type.label'),
            'engine_history' => __('app.listings.advanced.fields.engine_history.label'),
            'engine_condition' => __('app.listings.advanced.fields.engine_condition.label'),
            'restoration_history' => __('app.listings.advanced.fields.restoration_history.label'),
            'odometer' => __('app.listings.advanced.fields.odometer.label'),
            'license_plate_type' => __('app.listings.advanced.fields.license_plate_type.label'),
            'documentation_status' => __('app.listings.advanced.fields.documentation_status.label'),
            'exterior_color' => __('app.listings.advanced.fields.exterior_color.label'),
            'exterior_condition' => __('app.listings.advanced.fields.exterior_condition.label'),
            'interior_color' => __('app.listings.advanced.fields.interior_color.label'),
            'interior_condition' => __('app.listings.advanced.fields.interior_condition.label'),
            'seat_material' => __('app.listings.advanced.fields.seat_material.label'),
            'city' => __('app.listings.advanced.fields.city.label'),
            'state' => __('app.listings.advanced.fields.state.label'),
            'zipcode' => __('app.listings.advanced.fields.zipcode.label'),
            'price_from' => __('app.listings.advanced.fields.price_from.label'),
            'price_to' => __('app.listings.advanced.fields.price_to.label'),
        ];
    }

    /**
     * @return array<string, string|int|BackedEnum|null>
     */
    public function filters(): array
    {
        $filters = [];

        foreach (self::TEXT_FIELDS as $field) {
            $filters[$field] = $this->string($field)->trim()->toString();
        }

        foreach (self::INTEGER_FIELDS as $field) {
            $filters[$field] = $this->filled($field) ? $this->integer($field) : null;
        }

        foreach (self::ENUM_FIELDS as $field => $enum) {
            $filters[$field] = $this->enum($field, $enum);
        }

        return $filters;
    }

    public function hasFilters(): bool
    {
        foreach ($this->filters() as $value) {
            if ($value !== '' && $value !== null) {
                return true;
            }
        }

        return false;
    }
}
