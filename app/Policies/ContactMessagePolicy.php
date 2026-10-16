<?php

namespace App\Policies;

use App\Models\ContactMessage;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ContactMessagePolicy
{
    public function create(User $user): bool
    {
        return $user->isActiveAppUser();
    }

    public function updateStatus(User $user, ContactMessage $contactMessage): Response
    {
        return $user->isAdmin() ? Response::allow() : Response::deny();
    }
}
