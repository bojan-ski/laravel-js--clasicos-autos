<?php

namespace App\Support;

use App\Contracts\NavCounter;
use App\Models\User;
use App\Services\MessageReportService;

final readonly class OpenReportsCounter implements NavCounter
{
    public function __construct(private MessageReportService $reports) {}

    public function count(?User $user): int
    {
        return $user?->isAdmin() ? $this->reports->openCount() : 0;
    }
}
