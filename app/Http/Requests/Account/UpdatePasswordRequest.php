<?php

namespace App\Http\Requests\Account;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePasswordRequest extends FormRequest
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
            'old_password' => ['bail', 'required', 'string', 'current_password'],
            'password' => ['required', 'string', 'min:6', 'different:old_password', 'confirmed'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'old_password.current_password' => __('app.account.password.invalid'),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return route($this->user()->isAdmin() ? 'admin.account' : 'profile');
    }
}
