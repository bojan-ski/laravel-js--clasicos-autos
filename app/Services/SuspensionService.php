<?php

namespace App\Services;

use App\Enums\UserStatus;
use App\Exceptions\InvalidUserStatusTransitionException;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use LogicException;

class SuspensionService
{
    public function __construct(private UserSessionService $sessions) {}

    public function suspend(User $user, string $reason, ?int $days): void
    {
        if (! $user->isAppUser()) {
            throw new LogicException('Only app users can be suspended.');
        }

        $locked = DB::transaction(function () use ($user, $reason, $days): User {
            $locked = $this->lock($user);

            if ($locked->isSuspended()) {
                throw $this->invalid($locked, 'suspended');
            }

            $now = now();
            $locked->status = UserStatus::Suspended;
            $locked->suspended_at = $now;
            $locked->suspended_until = $days === null ? null : $now->copy()->addDays($days);
            $locked->suspension_reason = $reason;
            $locked->save();

            $this->sessions->endAll($locked);

            return $locked;
        });

        $this->sync($user, $locked);
    }

    public function unsuspend(User $user): void
    {
        $locked = DB::transaction(function () use ($user): User {
            $locked = $this->lock($user);

            if (! $locked->isSuspended()) {
                throw $this->invalid($locked, 'unsuspended');
            }

            $this->activate($locked);

            return $locked;
        });

        $this->sync($user, $locked);
    }

    public function liftIfExpired(User $user): bool
    {
        if (! $user->suspensionHasExpired()) {
            return false;
        }

        [$locked, $lifted] = DB::transaction(function () use ($user): array {
            $locked = User::query()->lockForUpdate()->find($user->getKey());

            if ($locked === null || ! $locked->suspensionHasExpired()) {
                return [$locked, false];
            }

            $this->activate($locked);

            return [$locked, true];
        });

        if ($locked !== null) {
            $this->sync($user, $locked);
        }

        return $lifted;
    }

    public function liftExpired(): int
    {
        $lifted = 0;

        User::query()->suspensionExpired()->lazyById()->each(function (User $user) use (&$lifted): void {
            if ($this->liftIfExpired($user)) {
                $lifted++;
            }
        });

        return $lifted;
    }

    private function activate(User $locked): void
    {
        $locked->status = UserStatus::Active;
        $locked->suspended_at = null;
        $locked->suspended_until = null;
        $locked->suspension_reason = null;
        $locked->save();
    }

    private function lock(User $user): User
    {
        return User::query()->lockForUpdate()->findOrFail($user->getKey());
    }

    private function sync(User $user, User $locked): void
    {
        $user->setRawAttributes($locked->getAttributes(), true);
    }

    private function invalid(User $user, string $transition): InvalidUserStatusTransitionException
    {
        return new InvalidUserStatusTransitionException(
            "User {$user->getKey()} cannot be {$transition} from status {$user->status->value}."
        );
    }
}
