<?php

namespace App\Enums;

enum ListingQueueFilter: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case All = 'all';

    public function status(): ?ListingStatus
    {
        return match ($this) {
            self::Pending => ListingStatus::Pending,
            self::Approved => ListingStatus::Approved,
            self::Rejected => ListingStatus::Rejected,
            self::All => null,
        };
    }

    public function queryValue(): ?string
    {
        return $this === self::Pending ? null : $this->value;
    }

    public function label(): string
    {
        return __("app.admin.listings.chips.{$this->value}");
    }

    public function emptyMessage(string $search, int $total): string
    {
        return $this === self::Pending && $search === '' && $total === 0
            ? __('app.admin.listings.empty_queue')
            : __('app.admin.listings.empty');
    }
}
