<?php

namespace App\Http\Requests\Admin;

use App\Models\CarListing;
use App\Support\ListingQueueQuery;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Gate;

abstract class ModerateListingRequest extends FormRequest
{
    abstract protected function ability(): string;

    public function authorize(): Response
    {
        return Gate::inspect($this->ability(), $this->listing());
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'version' => ['required', 'integer'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'version' => __('app.listings.moderation.attributes.version'),
        ];
    }

    public function version(): int
    {
        return (int) $this->validated('version');
    }

    public function returnUrl(): string
    {
        return ListingQueueQuery::returnsToQueue($this)
            ? route('admin.listings', ListingQueueQuery::from($this))
            : route('listings.show', $this->listing());
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            redirect()->to($this->returnUrl())->with('error', $validator->errors()->first())
        );
    }

    protected function listing(): CarListing
    {
        return $this->route('listing');
    }
}
