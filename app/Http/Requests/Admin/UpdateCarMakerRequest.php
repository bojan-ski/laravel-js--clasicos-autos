<?php

namespace App\Http\Requests\Admin;

use App\Models\CarMaker;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rules\Unique;

class UpdateCarMakerRequest extends StoreCarMakerRequest
{
    public function authorize(): Response
    {
        return Gate::inspect('update', $this->maker());
    }

    protected function prepareForValidation(): void
    {
        $this->errorBag = "maker-{$this->maker()->getKey()}";
    }

    protected function uniqueName(): Unique
    {
        return parent::uniqueName()->ignore($this->maker());
    }

    private function maker(): CarMaker
    {
        return $this->route('maker');
    }
}
