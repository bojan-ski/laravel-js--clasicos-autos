<?php

namespace App\Support;

use App\Contracts\NavCounter;
use App\Models\User;
use App\Services\MessagingService;

final readonly class UnreadMessagesCounter implements NavCounter
{
    public function __construct(private MessagingService $messaging) {}

    public function count(?User $user): int
    {
        return $user === null ? 0 : $this->messaging->unreadCountFor($user);
    }
}
