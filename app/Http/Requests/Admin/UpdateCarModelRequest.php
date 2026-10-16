<?php

namespace App\Http\Requests\Admin;

use App\Models\CarModel;
use Closure;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rules\Unique;
use Illuminate\Validation\Validator;

class UpdateCarModelRequest extends StoreCarModelRequest
{
    public function authorize(): Response
    {
        return Gate::inspect('update', $this->carModel());
    }

    /**
     * @return list<Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['name', 'year_from', 'year_to'])) {
                    return;
                }

                $years = $this->carModel()->listings()->distinct()->orderBy('year')->pluck('year')->map(fn (mixed $year): int => (int) $year);
                $below = $years->filter(fn (int $year): bool => $year < $this->integer('year_from'));
                $above = $years->filter(fn (int $year): bool => $year > $this->integer('year_to'));

                if ($below->isNotEmpty()) {
                    $validator->errors()->add('year_from', __('app.admin.models.years_below', ['years' => $below->implode(', ')]));
                }

                if ($above->isNotEmpty()) {
                    $validator->errors()->add('year_to', __('app.admin.models.years_above', ['years' => $above->implode(', ')]));
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->errorBag = "model-{$this->carModel()->getKey()}";
    }

    protected function uniqueName(): Unique
    {
        return parent::uniqueName()->ignore($this->carModel());
    }

    private function carModel(): CarModel
    {
        return $this->route('model');
    }
}
