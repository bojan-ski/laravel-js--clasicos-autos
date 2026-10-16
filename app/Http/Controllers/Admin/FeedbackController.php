<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ContactMessageStatus;
use App\Exceptions\InvalidContactMessageTransitionException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\FeedbackIndexRequest;
use App\Http\Requests\Admin\UpdateFeedbackStatusRequest;
use App\Models\ContactMessage;
use App\Services\ContactMessageService;
use App\Support\FeedbackQueueQuery;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class FeedbackController extends Controller
{
    public function index(FeedbackIndexRequest $request, ContactMessageService $contact): View
    {
        return view('pages.admin.feedback.index', [
            'contactMessages' => $contact->paginate($request->status()),
            'status' => $request->status(),
            'returnQuery' => FeedbackQueueQuery::from($request),
        ]);
    }

    public function updateStatus(UpdateFeedbackStatusRequest $request, ContactMessage $contactMessage, ContactMessageService $contact): RedirectResponse
    {
        $redirect = redirect()->route('admin.feedback', FeedbackQueueQuery::from($request));
        $target = $request->targetStatus();

        try {
            match ($target) {
                ContactMessageStatus::Handled => $contact->markHandled($contactMessage, $request->user()),
                ContactMessageStatus::New => $contact->markNew($contactMessage),
            };
        } catch (InvalidContactMessageTransitionException) {
            return $redirect->with('error', __('app.admin.feedback.flash.error'));
        }

        return $redirect->with('success', __("app.admin.feedback.flash.{$target->value}"));
    }
}
