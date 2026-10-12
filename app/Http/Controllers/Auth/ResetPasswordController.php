<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Services\PasswordResetService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ResetPasswordController extends Controller
{
    public function create(Request $request, string $token, PasswordResetService $resets): Response
    {
        $email = is_string($request->query('email')) ? $request->query('email') : '';

        return response()->view('pages.auth.reset-password', [
            'token' => $token,
            'email' => $email,
            'linkValid' => $resets->linkIsValid($token, $email),
        ])->withHeaders([
            'Referrer-Policy' => 'no-referrer',
            'Cache-Control' => 'no-store',
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
