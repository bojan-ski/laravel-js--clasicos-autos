<?php

namespace App\Http\Requests\Listings;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\Attributes\RedirectToRoute;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

#[RedirectToRoute('listings.index')]
class ListingIndexRequest extends FormRequest
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
            'search' => ['nullable', 'string', 'max:100'],
            'maker' => ['nullable', 'integer', Rule::exists('car_makers', 'id')->where('is_active', true)],
        ];
    }

    /**
     * @return array{search: string, maker: int|null}
     */
    public function filters(): array
    {
        return [
            'search' => $this->string('search')->trim()->toString(),
            'maker' => $this->filled('maker') ? $this->integer('maker') : null,
        ];
    }

    public function hasFilters(): bool
    {
        $filters = $this->filters();

        return $filters['search'] !== '' || $filters['maker'] !== null;
    }
}
