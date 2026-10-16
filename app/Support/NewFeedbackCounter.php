<?php

namespace App\Support;

use App\Contracts\NavCounter;
use App\Models\User;
use App\Services\ContactMessageService;

final readonly class NewFeedbackCounter implements NavCounter
{
    public function __construct(private ContactMessageService $contact) {}

    public function count(?User $user): int
    {
        return $user?->isAdmin() ? $this->contact->newCount() : 0;
    }
}
