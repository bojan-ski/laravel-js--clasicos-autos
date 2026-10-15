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
        private UserSessionService $sessions,
    ) {}

    public function delete(User $user): void
    {
        if ($user->isAdmin()) {
            throw new LogicException('Admin accounts cannot be deleted.');
        }

        DB::transaction(function () use ($user): void {
            $locked = User::query()->lockForUpdate()->findOrFail($user->getKey());

            $this->sessions->endAll($locked);
            $this->broker->deleteToken($locked);
            $listingIds = $locked->listings()->pluck('id')->all();
            $locked->delete();

            DB::afterCommit(fn () => $this->images->deleteListingDirectories($listingIds));
        });
    }
}
