<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;

class UserPolicy
{
    public function viewListings(User $viewer, User $seller): Response
    {
        if ($seller->isAdmin()) {
            return Response::denyAsNotFound();
        }

        return $viewer->seesEveryListingOf($seller) || ! $seller->isSuspended()
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    public function suspend(User $actor, User $target): Response
    {
        return $this->manages($actor, $target);
    }

    public function unsuspend(User $actor, User $target): Response
    {
        return $this->manages($actor, $target);
    }

    public function delete(User $actor, User $target): Response
    {
        return $this->manages($actor, $target);
    }

    private function manages(User $actor, User $target): Response
    {
        return $actor->isAdmin() && $target->isAppUser()
            ? Response::allow()
            : Response::deny();
    }
}
