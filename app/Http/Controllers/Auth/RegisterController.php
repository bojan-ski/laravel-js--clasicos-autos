<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Services\RegistrationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class RegisterController extends Controller
{
    public function create(): View
    {
        return view('pages.auth.register');
    }

    public function store(RegisterRequest $request, RegistrationService $registration): RedirectResponse
    {
        $registration->register($request->safe()->only(['username', 'email', 'phone_number', 'password']));

        return redirect()->route('login')->with('success', __('app.auth.flash.registered'));
    }
}
