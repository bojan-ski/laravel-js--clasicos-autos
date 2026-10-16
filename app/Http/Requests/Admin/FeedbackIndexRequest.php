<?php

namespace App\Http\Requests\Admin;

use App\Enums\ContactMessageStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\Attributes\RedirectToRoute;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

#[RedirectToRoute('admin.feedback')]
class FeedbackIndexRequest extends FormRequest
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
            'status' => ['nullable', Rule::enum(ContactMessageStatus::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'status' => __('app.admin.feedback.attributes.status'),
        ];
    }

    public function status(): ContactMessageStatus
    {
        return $this->enum('status', ContactMessageStatus::class) ?? ContactMessageStatus::New;
    }
}
