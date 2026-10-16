<?php

namespace App\Http\Requests\Admin;

use App\Models\CarMaker;
use App\Models\CarModel;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

class StoreCarModelRequest extends FormRequest
{
    public function authorize(): Response
    {
        return Gate::inspect('create', CarModel::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $years = 'between:'.config('listings.min_year').','.config('listings.max_year');

        return [
            'name' => ['required', 'string', 'max:64', $this->uniqueName()],
            'year_from' => ['required', 'integer', $years],
            'year_to' => ['required', 'integer', $years, 'gte:year_from'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return __('app.admin.models.attributes');
    }

    public function name(): string
    {
        return $this->validated('name');
    }

    public function yearFrom(): int
    {
        return (int) $this->validated('year_from');
    }

    public function yearTo(): int
    {
        return (int) $this->validated('year_to');
    }

    protected function getRedirectUrl(): string
    {
        return route('admin.makers.show', $this->maker());
    }

    protected function uniqueName(): Unique
    {
        return Rule::unique('car_models', 'name')->where('car_maker_id', $this->maker()->getKey());
    }

    protected function maker(): CarMaker
    {
        return $this->route('maker');
    }
}
