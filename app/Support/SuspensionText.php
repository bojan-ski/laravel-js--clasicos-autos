<?php

namespace App\Support;

use App\Models\User;

class SuspensionText
{
    public static function notice(User $user): string
    {
        if ($user->suspended_until === null) {
            return __('app.auth.login.suspended_indefinitely', ['reason' => $user->suspension_reason]);
        }

        return __('app.auth.login.suspended_until', [
            'date' => $user->suspended_until->format('d.m.Y'),
            'reason' => $user->suspension_reason,
        ]);
    }

    public static function status(User $user): string
    {
        if (! $user->isSuspended()) {
            return __('app.suspension.status.active');
        }

        if ($user->suspended_until === null) {
            return __('app.suspension.status.indefinite');
        }

        return __('app.suspension.status.until', ['date' => $user->suspended_until->format('d.m.Y')]);
    }

    /**
     * @return array<string, string>
     */
    public static function durationOptions(): array
    {
        $options = [];

        foreach (array_keys(config('listings.suspension.durations')) as $duration) {
            $options[$duration] = __("app.suspension.durations.{$duration}");
        }

        return $options;
    }
}
