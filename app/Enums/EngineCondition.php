<?php

namespace App\Enums;

use App\Contracts\ListingOption;
use Illuminate\Support\Str;

enum EngineCondition: string implements ListingOption
{
    case Running = 'Running';
    case NeedsTuning = 'Needs Tuning';
    case NotRunning = 'Not Running';
    case RebuiltEngine = 'Rebuilt Engine';
    case OriginalFactoryEngine = 'Original Factory Engine';

    public function label(): string
    {
        $key = Str::snake($this->name);

        return __("app.listings.options.engine_condition.{$key}");
    }
}
