<?php

namespace App\Enums;

enum ListingStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return __("app.listings.status.{$this->value}");
    }
}
