<?php

namespace App\View\Components\Listings;

use App\Support\ListingFormOptions;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class FormFields extends Component
{
    private const int UNSIGNED_INT_MAX = 4294967295;

    public function __construct(public ListingFormOptions $options) {}

    /**
     * @return list<array{id: string, number: string, title: string, note: string|null, fields: list<array{name: string, label: string, placeholder: string, type: string, required: bool, options: array<string, string>, min: int|null, max: int|null, maxlength: int|null, inputmode: string|null}>}>
     */
    public function sections(): array
    {
        return [
            $this->section('car', 'I', [
                $this->text('name', 255, required: true),
                $this->modelSelect(),
                $this->number('price', 0, self::UNSIGNED_INT_MAX, required: true),
                $this->number('year', config('listings.min_year'), config('listings.max_year'), required: true),
                $this->number('mileage', 0, self::UNSIGNED_INT_MAX, required: true),
                $this->select('transmission', required: true),
                $this->select('fuel_type', required: true),
            ], requiredNote: true),
            $this->section('engine', 'II', [
                $this->text('engine_size', 50),
                $this->text('engine_type', 100),
                $this->select('engine_history', required: true),
                $this->select('engine_condition', required: true),
            ]),
            $this->section('history', 'III', [
                $this->select('restoration_history', required: true),
                $this->number('original_parts_percentage', 0, 100),
                $this->select('odometer', required: true),
                $this->select('license_plate_type'),
                $this->select('documentation_status', required: true),
            ]),
            $this->section('looks', 'IV', [
                $this->select('body_type'),
                $this->text('exterior_color', 50),
                $this->select('exterior_condition'),
                $this->text('interior_color', 50),
                $this->select('interior_condition'),
                $this->select('seat_material'),
            ]),
            $this->section('location', 'V', [
                $this->text('location_city', 100, required: true),
                $this->text('location_state', 100, required: true),
                $this->text('location_zipcode', 20, required: true),
            ]),
        ];
    }

    public function render(): View
    {
        return view('components.listings.form-fields');
    }

    /**
     * @param  list<array{name: string, label: string, placeholder: string, type: string, required: bool, options: array<string, string>, min: int|null, max: int|null, maxlength: int|null, inputmode: string|null}>  $fields
     * @return array{id: string, number: string, title: string, note: string|null, fields: list<array{name: string, label: string, placeholder: string, type: string, required: bool, options: array<string, string>, min: int|null, max: int|null, maxlength: int|null, inputmode: string|null}>}
     */
    private function section(string $key, string $number, array $fields, bool $requiredNote = false): array
    {
        return [
            'id' => "section-{$key}",
            'number' => $number,
            'title' => __("app.listings.form.sections.{$key}.title"),
            'note' => $requiredNote ? null : __("app.listings.form.sections.{$key}.note"),
            'fields' => $fields,
        ];
    }

    /**
     * @return array{name: string, label: string, placeholder: string, type: string, required: bool, options: array<string, string>, min: int|null, max: int|null, maxlength: int|null, inputmode: string|null}
     */
    private function text(string $name, int $maxlength, bool $required = false): array
    {
        return $this->field($name, 'text', $required, maxlength: $maxlength);
    }

    /**
     * @return array{name: string, label: string, placeholder: string, type: string, required: bool, options: array<string, string>, min: int|null, max: int|null, maxlength: int|null, inputmode: string|null}
     */
    private function number(string $name, int $min, int $max, bool $required = false): array
    {
        return $this->field($name, 'number', $required, min: $min, max: $max, inputmode: 'numeric');
    }

    /**
     * @return array{name: string, label: string, placeholder: string, type: string, required: bool, options: array<string, string>, min: int|null, max: int|null, maxlength: int|null, inputmode: string|null}
     */
    private function select(string $name, bool $required = false): array
    {
        return $this->field($name, 'select', $required, options: $this->options->enums[$name]);
    }

    /**
     * @return array{name: string, label: string, placeholder: string, type: string, required: bool, options: array<string, string>, min: int|null, max: int|null, maxlength: int|null, inputmode: string|null}
     */
    private function modelSelect(): array
    {
        return $this->field('car_model_id', 'model-select', true);
    }

    /**
     * @param  array<string, string>  $options
     * @return array{name: string, label: string, placeholder: string, type: string, required: bool, options: array<string, string>, min: int|null, max: int|null, maxlength: int|null, inputmode: string|null}
     */
    private function field(
        string $name,
        string $type,
        bool $required,
        array $options = [],
        ?int $min = null,
        ?int $max = null,
        ?int $maxlength = null,
        ?string $inputmode = null,
    ): array {
        return [
            'name' => $name,
            'label' => __("app.listings.form.fields.{$name}.label"),
            'placeholder' => __("app.listings.form.fields.{$name}.placeholder"),
            'type' => $type,
            'required' => $required,
            'options' => $options,
            'min' => $min,
            'max' => $max,
            'maxlength' => $maxlength,
            'inputmode' => $inputmode,
        ];
    }
}
