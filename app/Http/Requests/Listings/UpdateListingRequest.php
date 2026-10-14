<?php

namespace App\Http\Requests\Listings;

use App\Models\CarListing;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

class UpdateListingRequest extends ListingRequest
{
    public function authorize(): Response
    {
        return Gate::inspect('update', $this->listing());
    }

    protected function getRedirectUrl(): string
    {
        return route('listings.edit', $this->listing());
    }

    protected function makerExistsRule(): Exists
    {
        $currentMakerId = $this->listing()->car_maker_id;

        return Rule::exists('car_makers', 'id')
            ->where(fn (Builder $query) => $query->where('is_active', true)->orWhere('id', $currentMakerId));
    }

    protected function modelExistsRule(): Exists
    {
        $currentModelId = $this->listing()->car_model_id;

        if ($currentModelId === null) {
            return parent::modelExistsRule();
        }

        return Rule::exists('car_models', 'id')
            ->where('car_maker_id', $this->integer('car_maker_id'))
            ->where(fn (Builder $query) => $query->where('is_active', true)->orWhere('id', $currentModelId));
    }

    private function listing(): CarListing
    {
        return $this->route('listing');
    }
}
