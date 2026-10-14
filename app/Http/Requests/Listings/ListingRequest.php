<?php

namespace App\Http\Requests\Listings;

use App\Models\CarModel;
use App\Support\ListingFormOptions;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Validator;

abstract class ListingRequest extends FormRequest
{
    protected const int UNSIGNED_INT_MAX = 4294967295;

    protected const array OPTIONAL_ENUM_FIELDS = ['license_plate_type', 'body_type', 'exterior_condition', 'interior_condition', 'seat_material'];

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'car_maker_id' => ['required', 'integer', $this->makerExistsRule()],
            'car_model_id' => [
                'required',
                Rule::when($this->input('car_model_id') !== ListingFormOptions::OTHER_MODEL, [
                    'integer',
                    $this->modelExistsRule(),
                ]),
            ],
            'model' => ['exclude_unless:car_model_id,'.ListingFormOptions::OTHER_MODEL, 'required', 'string', 'max:255'],
            'price' => ['required', 'integer', 'min:0', 'max:'.self::UNSIGNED_INT_MAX],
            'year' => ['required', 'integer', 'between:'.config('listings.min_year').','.config('listings.max_year')],
            'mileage' => ['required', 'integer', 'min:0', 'max:'.self::UNSIGNED_INT_MAX],
            'engine_size' => ['nullable', 'string', 'max:50'],
            'engine_type' => ['nullable', 'string', 'max:100'],
            'original_parts_percentage' => ['nullable', 'integer', 'between:0,100'],
            'exterior_color' => ['nullable', 'string', 'max:50'],
            'interior_color' => ['nullable', 'string', 'max:50'],
            'location_city' => ['required', 'string', 'max:100'],
            'location_state' => ['required', 'string', 'max:100'],
            'location_zipcode' => ['required', 'string', 'max:20'],
            'description' => ['required', 'string', 'max:5000'],
        ];

        foreach (ListingFormOptions::ENUM_FIELDS as $field => $enum) {
            $rules[$field] = [in_array($field, self::OPTIONAL_ENUM_FIELDS, true) ? 'nullable' : 'required', Rule::enum($enum)];
        }

        return array_merge(array_fill_keys(ListingFormOptions::FIELDS, []), $rules);
    }

    /**
     * @return list<Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['year', 'car_maker_id', 'car_model_id']) || $this->input('car_model_id') === ListingFormOptions::OTHER_MODEL) {
                    return;
                }

                $model = CarModel::query()->find($this->integer('car_model_id'));

                if ($model !== null && ! $model->coversYear($this->integer('year'))) {
                    $validator->errors()->add('year', __('app.listings.form.production_years', [
                        'model' => $model->name,
                        'years' => $model->productionYears(),
                    ]));
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $attributes = [];

        foreach (ListingFormOptions::FIELDS as $field) {
            $attributes[$field] = __("app.listings.form.fields.{$field}.label");
        }

        return $attributes;
    }

    /**
     * @return array<string, mixed>
     */
    public function listingData(): array
    {
        $data = $this->safe()->only(ListingFormOptions::FIELDS);
        $isOther = $data['car_model_id'] === ListingFormOptions::OTHER_MODEL;

        $data['car_model_id'] = $isOther ? null : (int) $data['car_model_id'];

        if (! $isOther) {
            unset($data['model']);
        }

        return $data;
    }

    protected function makerExistsRule(): Exists
    {
        return Rule::exists('car_makers', 'id')->where('is_active', true);
    }

    protected function modelExistsRule(): Exists
    {
        return Rule::exists('car_models', 'id')
            ->where('car_maker_id', $this->integer('car_maker_id'))
            ->where('is_active', true);
    }
}
