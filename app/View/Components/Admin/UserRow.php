<?php

namespace App\View\Components\Admin;

use App\Models\User;
use App\Support\SuspensionText;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class UserRow extends Component
{
    /**
     * @param  array{search?: string, page?: int}  $returnQuery
     */
    public function __construct(public User $user, public array $returnQuery) {}

    public function created(): string
    {
        return $this->user->created_at->format('d.m.Y');
    }

    public function status(): string
    {
        return SuspensionText::status($this->user);
    }

    public function isSuspended(): bool
    {
        return $this->user->isSuspended();
    }

    public function listingsUrl(): string
    {
        return route('users.listings', $this->user);
    }

    public function suspendUrl(): string
    {
        return route('admin.users.suspend', [$this->user, ...$this->returnQuery]);
    }

    public function unsuspendUrl(): string
    {
        return route('admin.users.unsuspend', [$this->user, ...$this->returnQuery]);
    }

    public function destroyUrl(): string
    {
        return route('admin.users.destroy', [$this->user, ...$this->returnQuery]);
    }

    /**
     * @return array<string, string>
     */
    public function durationOptions(): array
    {
        return SuspensionText::durationOptions();
    }

    public function render(): View
    {
        return view('components.admin.user-row');
    }
}
