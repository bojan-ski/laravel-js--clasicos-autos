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
}
