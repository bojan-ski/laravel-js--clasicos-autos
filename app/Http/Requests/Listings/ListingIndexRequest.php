<?php

namespace App\Http\Requests\Listings;

use App\Models\CarMaker;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\Attributes\RedirectToRoute;
use Illuminate\Foundation\Http\FormRequest;

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
            'maker' => ['nullable', 'array', 'list', $this->activeMakers(...)],
        ];
    }

    /**
     * @return array{search: string, makers: list<int>}
     */
    public function filters(): array
    {
        $makers = array_values(array_unique(array_map(intval(...), (array) $this->input('maker', []))));
        sort($makers);

        return [
            'search' => $this->string('search')->trim()->toString(),
            'makers' => $makers,
        ];
    }

    public function hasFilters(): bool
    {
        $filters = $this->filters();

        return $filters['search'] !== '' || $filters['makers'] !== [];
    }

    protected function prepareForValidation(): void
    {
        $maker = $this->input('maker');

        if (is_string($maker) && $maker !== '') {
            $this->merge(['maker' => [$maker]]);
        }
    }

    private function activeMakers(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_array($value)) {
            return;
        }

        $integers = array_filter($value, fn (mixed $id): bool => filter_var($id, FILTER_VALIDATE_INT) !== false);
        $ids = array_unique(array_map(intval(...), $integers));

        if (count($integers) !== count($value) || CarMaker::query()->active()->whereKey($ids)->count() !== count($ids)) {
            $fail('validation.exists')->translate();
        }
    }
}
