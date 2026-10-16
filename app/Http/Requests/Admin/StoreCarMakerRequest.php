<?php

namespace App\Http\Requests\Admin;

use App\Models\CarMaker;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\Attributes\RedirectToRoute;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

#[RedirectToRoute('admin.makers')]
class StoreCarMakerRequest extends FormRequest
{
    public function authorize(): Response
    {
        return Gate::inspect('create', CarMaker::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:64', $this->uniqueName()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return __('app.admin.makers.attributes');
    }

    public function name(): string
    {
        return $this->validated('name');
    }

    protected function uniqueName(): Unique
    {
        return Rule::unique('car_makers', 'name');
    }
}
