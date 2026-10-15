<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use App\Support\UserTableQuery;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class SuspendUserRequest extends FormRequest
{
    public function authorize(): Response
    {
        return Gate::inspect('suspend', $this->target());
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:500'],
            'duration' => ['required', 'string', Rule::in(array_keys(config('listings.suspension.durations')))],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'reason' => __('app.admin.users.attributes.reason'),
            'duration' => __('app.admin.users.attributes.duration'),
        ];
    }

    public function reason(): string
    {
        return $this->validated('reason');
    }

    public function days(): ?int
    {
        return config('listings.suspension.durations')[$this->validated('duration')];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            redirect()->route('admin.users', UserTableQuery::from($this))->with('error', $validator->errors()->first())
        );
    }

    private function target(): User
    {
        return $this->route('user');
    }
}
