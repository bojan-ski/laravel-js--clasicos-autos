<?php

namespace App\Http\Controllers\Contact;

use App\Http\Controllers\Controller;
use App\Http\Requests\Contact\SendContactMessageRequest;
use App\Services\ContactMessageService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    public function show(Request $request): View
    {
        return view('pages.contact.show', [
            'replyEmail' => $request->user()->email,
        ]);
    }

    public function store(SendContactMessageRequest $request, ContactMessageService $contact): RedirectResponse
    {
        $contact->send($request->user(), $request->validated('subject'), $request->validated('message'));

        return redirect()->route('contact')->with('success', __('app.contact.flash.sent'));
    }
}
