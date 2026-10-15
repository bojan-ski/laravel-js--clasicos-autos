<?php

namespace App\Support;

use App\Contracts\NavCounter;
use App\Models\User;
use App\Services\ListingQueueService;

final readonly class PendingListingsCounter implements NavCounter
{
    public function __construct(private ListingQueueService $queue) {}

    public function count(?User $user): int
    {
        return $user?->isAdmin() ? $this->queue->pendingCount() : 0;
    }
}
