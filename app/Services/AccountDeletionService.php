<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Auth\Passwords\PasswordBroker;
use Illuminate\Support\Facades\DB;
use LogicException;

class AccountDeletionService
{
    public function __construct(
        private PasswordBroker $broker,
        private ListingImageService $images,
    ) {}

    public function delete(User $user): void
    {
        if ($user->isAdmin()) {
            throw new LogicException('Admin accounts cannot be deleted.');
        }

        $this->broker->deleteToken($user);
        $listingIds = $user->listings()->pluck('id')->all();
        $user->delete();
        DB::afterCommit(fn () => $this->images->deleteListingDirectories($listingIds));
    }
}
