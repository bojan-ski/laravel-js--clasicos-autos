<?php

namespace App\Http\Requests\Admin;

use App\Models\CarMaker;
use App\Models\CarModel;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Gate;

class SetReferenceActiveRequest extends FormRequest
{
    public function authorize(): Response
    {
        return Gate::inspect('update', $this->carModel() ?? $this->maker());
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'active' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'active' => __('app.admin.makers.attributes.active'),
        ];
    }

    public function active(): bool
    {
        return $this->boolean('active');
    }

    protected function failedValidation(Validator $validator): void
    {
        $page = $this->carModel() === null ? route('admin.makers') : route('admin.makers.show', $this->maker());

        throw new HttpResponseException(redirect()->to($page)->with('error', $validator->errors()->first()));
    }

    private function maker(): CarMaker
    {
        return $this->route('maker');
    }

    private function carModel(): ?CarModel
    {
        return $this->route('model');
    }
}
