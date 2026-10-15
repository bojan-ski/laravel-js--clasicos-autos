<?php

namespace App\Http\Requests\Admin;

use App\Enums\ReportStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\Attributes\RedirectToRoute;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

#[RedirectToRoute('admin.reports')]
class ReportIndexRequest extends FormRequest
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
            'status' => ['nullable', Rule::enum(ReportStatus::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'status' => __('app.admin.reports.attributes.status'),
        ];
    }

    public function status(): ReportStatus
    {
        return $this->enum('status', ReportStatus::class) ?? ReportStatus::Open;
    }
}
