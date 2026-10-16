<?php

namespace App\Http\Requests\Admin;

use App\Enums\ContactMessageStatus;
use App\Models\ContactMessage;
use App\Support\FeedbackQueueQuery;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateFeedbackStatusRequest extends FormRequest
{
    public function authorize(): Response
    {
        return Gate::inspect('updateStatus', $this->contactMessage());
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'target_status' => ['required', 'string', Rule::enum(ContactMessageStatus::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'target_status' => __('app.admin.feedback.attributes.target_status'),
        ];
    }

    public function targetStatus(): ContactMessageStatus
    {
        return ContactMessageStatus::from($this->validated('target_status'));
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            redirect()->route('admin.feedback', FeedbackQueueQuery::from($this))->with('error', $validator->errors()->first())
        );
    }

    private function contactMessage(): ContactMessage
    {
        return $this->route('contactMessage');
    }
}
