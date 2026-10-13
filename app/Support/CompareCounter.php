<?php

namespace App\Support;

use App\Contracts\NavCounter;
use App\Models\User;
use App\Services\CompareService;

final readonly class CompareCounter implements NavCounter
{
    public function __construct(private CompareService $compare) {}

    public function count(?User $user): int
    {
        return count($this->compare->ids());
    }
}
