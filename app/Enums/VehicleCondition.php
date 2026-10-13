<?php

namespace App\Enums;

use App\Contracts\ListingOption;
use Illuminate\Support\Str;

enum VehicleCondition: string implements ListingOption
{
    case ShowroomCondition = 'Showroom Condition';
    case Good = 'Good';
    case Fair = 'Fair';
    case NeedsRestoration = 'Needs Restoration';

    public function label(): string
    {
        $key = Str::snake($this->name);

        return __("app.listings.options.vehicle_condition.{$key}");
    }
}
