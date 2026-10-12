<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Account\UpdatePasswordRequest;
use App\Services\PasswordService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class AccountController extends Controller
{
    public function show(): View
    {
        return view('pages.admin.account');
    }

    public function updatePassword(UpdatePasswordRequest $request, PasswordService $passwords): RedirectResponse
    {
        $passwords->update($request->user(), $request->validated('password'));

        return redirect()->route('admin.account')->with('success', __('app.account.password.updated'));
    }
}
