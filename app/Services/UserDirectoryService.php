<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\User;
use App\Support\LikePattern;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class UserDirectoryService
{
    /**
     * @return LengthAwarePaginator<int, User>
     */
    public function appUsers(string $search): LengthAwarePaginator
    {
        return User::query()
            ->where('role', UserRole::AppUser)
            ->when($search !== '', function (Builder $query) use ($search): void {
                $pattern = LikePattern::contains($search);

                $query->where(fn (Builder $match) => $match
                    ->where('username', 'like', $pattern)
                    ->orWhere('email', 'like', $pattern));
            })
            ->withCount('listings')
            ->latest()
            ->orderByDesc('id')
            ->paginate(config('listings.per_page.admin'))
            ->withQueryString();
    }
}
