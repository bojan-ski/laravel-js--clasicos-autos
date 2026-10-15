<?php

namespace App\Http\Requests\Admin;

use App\Models\CarModel;
use App\Support\ListingQueueQuery;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Validator;

class PromoteListingModelRequest extends ModerateListingRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $years = 'between:'.config('listings.min_year').','.config('listings.max_year');

        return [
            'name' => ['required', 'string', 'max:64'],
            'year_from' => ['required', 'integer', $years],
            'year_to' => ['required', 'integer', $years, 'gte:year_from'],
            ...parent::rules(),
        ];
    }

    /**
     * @return list<Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['name', 'year_from', 'year_to']) || ! $this->listing()->canPromoteModel()) {
                    return;
                }

                $year = $this->listing()->year;
                $match = CarModel::query()
                    ->where('car_maker_id', $this->listing()->car_maker_id)
                    ->where('name', $this->string('name')->toString())
                    ->first();

                if ($match !== null && ! $match->coversYear($year)) {
                    $validator->errors()->add('name', __('app.admin.listings.promote.years_mismatch', [
                        'model' => $match->name,
                        'years' => $match->productionYears(),
                        'year' => $year,
                    ]));
                }

                if ($match === null && ($this->integer('year_from') > $year || $this->integer('year_to') < $year)) {
                    $validator->errors()->add('year_from', __('app.admin.listings.promote.range_excludes_year', ['year' => $year]));
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            ...__('app.admin.listings.promote.attributes'),
            ...parent::attributes(),
        ];
    }

    public function returnUrl(): string
    {
        return route('admin.listings', ListingQueueQuery::from($this));
    }

    public function name(): string
    {
        return $this->validated('name');
    }

    public function yearFrom(): int
    {
        return (int) $this->validated('year_from');
    }

    public function yearTo(): int
    {
        return (int) $this->validated('year_to');
    }

    protected function ability(): string
    {
        return 'promoteModel';
    }
}
