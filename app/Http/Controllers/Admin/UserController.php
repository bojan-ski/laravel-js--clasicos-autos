<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\InvalidUserStatusTransitionException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SuspendUserRequest;
use App\Http\Requests\Admin\UserIndexRequest;
use App\Models\User;
use App\Services\AccountDeletionService;
use App\Services\SuspensionService;
use App\Services\UserDirectoryService;
use App\Support\UserTableQuery;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class UserController extends Controller
{
    public function index(UserIndexRequest $request, UserDirectoryService $directory): View
    {
        return view('pages.admin.users.index', [
            'users' => $directory->appUsers($request->search()),
            'search' => $request->search(),
            'returnQuery' => UserTableQuery::from($request),
        ]);
    }

    public function suspend(SuspendUserRequest $request, User $user, SuspensionService $suspensions): RedirectResponse
    {
        return $this->transition(
            $request,
            fn () => $suspensions->suspend($user, $request->reason(), $request->days(), $request->user()),
            __('app.admin.users.flash.suspended', ['username' => $user->username]),
            __('app.admin.users.flash.already_suspended'),
        );
    }

    public function unsuspend(Request $request, User $user, SuspensionService $suspensions): RedirectResponse
    {
        Gate::authorize('unsuspend', $user);

        return $this->transition(
            $request,
            fn () => $suspensions->unsuspend($user),
            __('app.admin.users.flash.unsuspended', ['username' => $user->username]),
            __('app.admin.users.flash.not_suspended'),
        );
    }

    public function destroy(Request $request, User $user, AccountDeletionService $deletion): RedirectResponse
    {
        Gate::authorize('delete', $user);

        $deletion->delete($user);

        return redirect()->route('admin.users', UserTableQuery::from($request))
            ->with('success', __('app.admin.users.flash.deleted'));
    }

    private function transition(Request $request, Closure $transition, string $success, string $wrongState): RedirectResponse
    {
        $redirect = redirect()->route('admin.users', UserTableQuery::from($request));

        try {
            $transition();
        } catch (InvalidUserStatusTransitionException) {
            return $redirect->with('error', $wrongState);
        }

        return $redirect->with('success', $success);
    }
}
