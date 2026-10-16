<?php

namespace App\Support\Seo;

use App\Models\CarListing;
use App\Models\ListingImage;

final class ListingSeo
{
    public static function for(CarListing $listing): PageSeo
    {
        $description = MetaDescription::excerpt($listing->description);
        $firstImage = $listing->images->first();
        $card = config('listings.images.variants.card');

        return new PageSeo(
            description: $description ?? __('app.seo.listings_description'),
            breadcrumbs: PageSeo::trail(
                ['name' => __('app.nav.listings'), 'url' => CanonicalUrl::route('listings.index')],
                ['name' => $listing->name, 'url' => CanonicalUrl::route('listings.show', $listing)],
            ),
            image: $firstImage === null ? null : new SeoImage($firstImage->cardUrl(), $card['width'], $card['height'], $listing->name),
            preloadImageUrl: $firstImage?->url(),
            structuredData: $listing->isPublic() ? [self::car($listing, $description, $firstImage)] : [],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private static function car(CarListing $listing, ?string $description, ?ListingImage $firstImage): array
    {
        $url = CanonicalUrl::route('listings.show', $listing);

        $car = [
            '@type' => 'Car',
            '@id' => $url.'#car',
            'name' => $listing->name,
            'description' => $description,
            'url' => $url,
            'image' => $firstImage === null ? null : CanonicalUrl::for($firstImage->url()),
            'brand' => ['@type' => 'Brand', 'name' => $listing->maker->name],
            'model' => $listing->model,
            'vehicleModelDate' => $listing->year === null ? null : (string) $listing->year,
            'mileageFromOdometer' => $listing->mileage === null ? null : ['@type' => 'QuantitativeValue', 'value' => $listing->mileage, 'unitCode' => 'KMT'],
            'color' => $listing->exterior_color,
            'vehicleTransmission' => $listing->transmission?->label(),
            'fuelType' => $listing->fuel_type?->label(),
            'bodyType' => $listing->body_type?->label(),
            'offers' => [
                '@type' => 'Offer',
                'price' => $listing->price,
                'priceCurrency' => 'USD',
                'availability' => 'https://schema.org/InStock',
                'url' => $url,
            ],
        ];

        return array_filter($car, fn (mixed $value): bool => $value !== null);
    }
}
