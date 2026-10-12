<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\Account\DeleteAccountRequest;
use App\Http\Requests\Account\UpdatePasswordRequest;
use App\Services\AccountDeletionService;
use App\Services\PasswordService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class ProfileController extends Controller
{
    public function show(Request $request): View
    {
        $user = $request->user();

        return view('pages.profile.show', [
            'user' => $user,
            'heading' => __('app.profile.heading', ['username' => Str::upper($user->username)]),
            'accountCreated' => $user->created_at->format('d.m.Y'),
        ]);
    }

    public function updatePassword(UpdatePasswordRequest $request, PasswordService $passwords): RedirectResponse
    {
        $passwords->update($request->user(), $request->validated('password'));

        return redirect()->route('profile')->with('success', __('app.account.password.updated'));
    }

    public function destroy(DeleteAccountRequest $request, AccountDeletionService $deletion): RedirectResponse
    {
        $user = $request->user();

        Auth::guard('web')->logout();
        $deletion->delete($user);

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')->with('success', __('app.profile.delete.deleted'));
    }
}
