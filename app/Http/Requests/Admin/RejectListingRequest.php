<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;

class RejectListingRequest extends ModerateListingRequest
{
    public const int REASON_MAX = 1000;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'rejection_reason' => ['required', 'string', 'max:'.self::REASON_MAX],
            ...parent::rules(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'rejection_reason' => __('app.listings.moderation.attributes.rejection_reason'),
            ...parent::attributes(),
        ];
    }

    public function reason(): string
    {
        return $this->validated('rejection_reason');
    }

    protected function ability(): string
    {
        return 'reject';
    }
}
