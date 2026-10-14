<?php

namespace App\Http\Requests\Listings;

use App\Models\CarListing;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;

class StoreListingImagesRequest extends FormRequest
{
    public function authorize(): Response
    {
        return Gate::inspect('update', $this->listing());
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'images' => ['required', 'array', 'min:1', 'max:'.(config('listings.images.max') - 1)],
            'images.*' => ['required', 'image', 'mimes:jpeg,jpg,png', 'max:'.config('listings.images.max_upload_kb')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $images = __('app.listings.images.add_label');

        return ['images' => $images, 'images.*' => $images];
    }

    /**
     * @return list<UploadedFile>
     */
    public function images(): array
    {
        return array_values($this->file('images', []));
    }

    protected function getRedirectUrl(): string
    {
        return route('listings.images.edit', $this->listing());
    }

    private function listing(): CarListing
    {
        return $this->route('listing');
    }
}
