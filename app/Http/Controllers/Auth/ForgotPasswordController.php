<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Services\PasswordResetService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class ForgotPasswordController extends Controller
{
    public function create(): View
    {
        return view('pages.auth.forgot-password');
    }

    public function store(ForgotPasswordRequest $request, PasswordResetService $resets): RedirectResponse
    {
        $resets->sendLink($request->validated('email'));

        return redirect()->route('password.request')->with('success', __('app.auth.forgot.sent'));
    }
}
