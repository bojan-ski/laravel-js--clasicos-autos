<?php

namespace App\Http\Requests\Conversations;

use App\Enums\ReportReason;
use App\Models\Message;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ReportMessageRequest extends FormRequest
{
    public function authorize(): Response
    {
        return Gate::inspect('report', $this->message());
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', Rule::enum(ReportReason::class)],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'reason' => __('app.conversations.report.attributes.reason'),
            'note' => __('app.conversations.report.attributes.note'),
        ];
    }

    public function reason(): ReportReason
    {
        return ReportReason::from($this->validated('reason'));
    }

    public function note(): ?string
    {
        $note = trim((string) $this->validated('note'));

        return $note === '' ? null : $note;
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            redirect()->route('conversations.show', $this->route('conversation'))
                ->withFragment('message-'.$this->message()->id)
                ->with('error', $validator->errors()->first())
        );
    }

    private function message(): Message
    {
        return $this->route('message');
    }
}
