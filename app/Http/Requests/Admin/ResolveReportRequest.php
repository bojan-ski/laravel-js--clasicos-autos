<?php

namespace App\Http\Requests\Admin;

use App\Enums\ReportOutcome;
use App\Models\MessageReport;
use App\Support\ReportQueueQuery;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ResolveReportRequest extends FormRequest
{
    public function authorize(): Response
    {
        $response = Gate::inspect('resolve', $this->report());

        if ($response->denied() || $this->input('outcome') !== ReportOutcome::UserSuspended->value) {
            return $response;
        }

        return Gate::inspect('suspend', $this->report()->reportedUser);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'outcome' => ['required', 'string', Rule::enum(ReportOutcome::class)],
            'reason' => ['exclude_unless:outcome,'.ReportOutcome::UserSuspended->value, 'required', 'string', 'max:500'],
            'duration' => [
                'exclude_unless:outcome,'.ReportOutcome::UserSuspended->value,
                'required',
                'string',
                Rule::in(array_keys(config('listings.suspension.durations'))),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'outcome' => __('app.admin.reports.attributes.outcome'),
            'reason' => __('app.admin.reports.attributes.reason'),
            'duration' => __('app.admin.reports.attributes.duration'),
        ];
    }

    public function outcome(): ReportOutcome
    {
        return ReportOutcome::from($this->validated('outcome'));
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
            redirect()->route('admin.reports', ReportQueueQuery::from($this))->with('error', $validator->errors()->first())
        );
    }

    private function report(): MessageReport
    {
        return $this->route('report');
    }
}
