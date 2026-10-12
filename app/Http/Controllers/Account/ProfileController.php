<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function show(Request $request): View
    {
        return view('pages.profile.show', [
            'user' => $request->user(),
        ]);
    }
}
