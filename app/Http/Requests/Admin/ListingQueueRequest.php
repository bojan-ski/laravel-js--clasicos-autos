<?php

namespace App\Http\Requests\Admin;

use App\Enums\ListingQueueFilter;
use App\Support\ListingQueueQuery;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\Attributes\RedirectToRoute;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

#[RedirectToRoute('admin.listings')]
class ListingQueueRequest extends FormRequest
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
            'status' => ['nullable', Rule::enum(ListingQueueFilter::class)],
            'search' => ['nullable', 'string', 'max:'.ListingQueueQuery::MAX_SEARCH],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'status' => __('app.admin.listings.attributes.status'),
            'search' => __('app.admin.listings.attributes.search'),
        ];
    }

    public function filter(): ListingQueueFilter
    {
        return $this->enum('status', ListingQueueFilter::class) ?? ListingQueueFilter::Pending;
    }

    public function search(): string
    {
        return $this->string('search')->trim()->toString();
    }
}
