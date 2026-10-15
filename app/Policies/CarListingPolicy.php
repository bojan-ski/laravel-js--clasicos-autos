<?php

namespace App\Policies;

use App\Models\CarListing;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class CarListingPolicy
{
    public function view(?User $user, CarListing $listing): Response
    {
        return $this->isVisibleTo($user, $listing) ? Response::allow() : Response::denyAsNotFound();
    }

    public function compare(?User $user, CarListing $listing): Response
    {
        return $this->isPublic($listing) ? Response::allow() : Response::denyAsNotFound();
    }

    public function create(User $user): bool
    {
        return $user->isActiveAppUser();
    }

    public function update(User $user, CarListing $listing): Response
    {
        return $this->ownerOrAdmin($user, $listing);
    }

    public function delete(User $user, CarListing $listing): Response
    {
        return $this->ownerOrAdmin($user, $listing);
    }

    public function approve(User $user, CarListing $listing): Response
    {
        return $this->adminOnly($user, $listing);
    }

    public function reject(User $user, CarListing $listing): Response
    {
        return $this->adminOnly($user, $listing);
    }

    public function promoteModel(User $user, CarListing $listing): Response
    {
        return $this->adminOnly($user, $listing);
    }

    public function bookmark(User $user, CarListing $listing): Response
    {
        if (! $this->isVisibleTo($user, $listing)) {
            return Response::denyAsNotFound();
        }

        return ($user->isAppUser() || $user->isAdmin()) && ! $listing->isOwnedBy($user) && $this->isPublic($listing)
            ? Response::allow()
            : Response::deny();
    }

    public function message(User $user, CarListing $listing): Response
    {
        if (! $this->isVisibleTo($user, $listing)) {
            return Response::denyAsNotFound();
        }

        return $user->isActiveAppUser() && ! $listing->isOwnedBy($user) && $this->isPublic($listing)
            ? Response::allow()
            : Response::deny();
    }

    private function ownerOrAdmin(User $user, CarListing $listing): Response
    {
        if (! $this->isVisibleTo($user, $listing)) {
            return Response::denyAsNotFound();
        }

        return $listing->isOwnedBy($user) || $user->isAdmin() ? Response::allow() : Response::deny();
    }

    private function adminOnly(User $user, CarListing $listing): Response
    {
        if (! $this->isVisibleTo($user, $listing)) {
            return Response::denyAsNotFound();
        }

        return $user->isAdmin() ? Response::allow() : Response::deny();
    }

    private function isVisibleTo(?User $user, CarListing $listing): bool
    {
        if ($user !== null && ($user->isAdmin() || $listing->isOwnedBy($user))) {
            return true;
        }

        return $this->isPublic($listing);
    }

    private function isPublic(CarListing $listing): bool
    {
        return $listing->isApproved() && $listing->loadMissing('owner')->isPublic();
    }
}
