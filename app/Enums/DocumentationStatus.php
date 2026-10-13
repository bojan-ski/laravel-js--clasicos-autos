<?php

namespace App\Enums;

use App\Contracts\ListingOption;
use Illuminate\Support\Str;

enum DocumentationStatus: string implements ListingOption
{
    case OriginalPapers = 'Original Papers';
    case MissingPapers = 'Missing Papers';
    case CustomRegistration = 'Custom Registration';

    public function label(): string
    {
        $key = Str::snake($this->name);

        return __("app.listings.options.documentation_status.{$key}");
    }
}
