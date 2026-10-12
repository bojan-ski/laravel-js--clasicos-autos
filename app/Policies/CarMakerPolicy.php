<?php

namespace App\Policies;

use App\Models\CarMaker;
use App\Models\User;

class CarMakerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, CarMaker $carMaker): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, CarMaker $carMaker): bool
    {
        return $user->isAdmin() && $carMaker->isDeletable();
    }
}
