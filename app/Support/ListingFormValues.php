<?php

namespace App\Support;

use App\Models\CarListing;
use BackedEnum;

final class ListingFormValues
{
    /**
     * @return array<string, int|string|null>
     */
    public static function for(CarListing $listing): array
    {
        $values = [];

        foreach (ListingFormOptions::FIELDS as $field) {
            $value = $listing->getAttribute($field);
            $values[$field] = $value instanceof BackedEnum ? $value->value : $value;
        }

        $values['car_model_id'] = $listing->hasCustomModel() ? ListingFormOptions::OTHER_MODEL : $listing->car_model_id;
        $values['model'] = $listing->hasCustomModel() ? $listing->model : null;

        return $values;
    }
}
