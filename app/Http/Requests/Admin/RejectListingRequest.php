<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;

class RejectListingRequest extends ModerateListingRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'rejection_reason' => ['required', 'string', 'max:1000'],
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
