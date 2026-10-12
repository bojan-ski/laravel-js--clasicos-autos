<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Services\PasswordResetService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ResetPasswordController extends Controller
{
    public function create(Request $request, string $token, PasswordResetService $resets): View
    {
        $email = is_string($request->query('email')) ? $request->query('email') : '';

        return view('pages.auth.reset-password', [
            'token' => $token,
            'email' => $email,
            'linkValid' => $resets->linkIsValid($token, $email),
        ]);
    }

    public function store(ResetPasswordRequest $request, PasswordResetService $resets): RedirectResponse
    {
        ['token' => $token, 'email' => $email, 'password' => $password] = $request->validated();

        if (! $resets->reset($token, $email, $password)) {
            return redirect()->route('password.reset', ['token' => $token, 'email' => $email]);
        }

        return redirect()->route('login')->with('success', __('app.auth.flash.password_reset'));
    }
}
