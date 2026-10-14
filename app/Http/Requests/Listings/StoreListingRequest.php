<?php

namespace App\Http\Requests\Listings;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\Attributes\RedirectToRoute;
use Illuminate\Http\UploadedFile;

#[RedirectToRoute('listings.create')]
class StoreListingRequest extends ListingRequest
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
            ...parent::rules(),
            'images' => ['required', 'array', 'min:1', 'max:'.config('listings.images.max')],
            'images.*' => ['required', 'image', 'mimes:jpeg,jpg,png', 'max:'.config('listings.images.max_upload_kb')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $images = __('app.listings.form.fields.images.label', ['max' => config('listings.images.max')]);

        return [...parent::attributes(), 'images' => $images, 'images.*' => $images];
    }

    /**
     * @return list<UploadedFile>
     */
    public function images(): array
    {
        return array_values($this->file('images', []));
    }
}
