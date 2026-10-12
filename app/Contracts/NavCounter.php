<?php

namespace App\Contracts;

use App\Models\User;

interface NavCounter
{
    public function count(?User $user): int;
}
