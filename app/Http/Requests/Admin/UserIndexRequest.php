<?php

namespace App\Http\Requests\Admin;

use App\Support\UserTableQuery;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\Attributes\RedirectToRoute;
use Illuminate\Foundation\Http\FormRequest;

#[RedirectToRoute('admin.users')]
class UserIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:'.UserTableQuery::MAX_SEARCH],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'search' => __('app.admin.users.attributes.search'),
        ];
    }

    public function search(): string
    {
        return $this->string('search')->trim()->toString();
    }
}
