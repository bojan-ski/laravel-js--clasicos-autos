<?php

namespace App\Http\Controllers\Conversations;

use App\Exceptions\MessageAlreadyReportedException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Conversations\ReportMessageRequest;
use App\Http\Requests\Conversations\StoreMessageRequest;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\MessageReportService;
use App\Services\MessagingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class MessageController extends Controller
{
    public function store(StoreMessageRequest $request, Conversation $conversation, MessagingService $messaging): RedirectResponse
    {
        $message = $messaging->send($conversation, $request->user(), $request->text());

        return redirect()->route('conversations.show', $conversation)->withFragment('message-'.$message->id);
    }

    public function report(ReportMessageRequest $request, Conversation $conversation, Message $message, MessageReportService $reports): RedirectResponse
    {
        $redirect = redirect()->route('conversations.show', $conversation)->withFragment('message-'.$message->id);

        try {
            $reports->report($message, $request->user(), $request->reason(), $request->note());
        } catch (MessageAlreadyReportedException) {
            return $redirect->with('error', __('app.conversations.report.flash.already_reported'));
        }

        return $redirect->with('success', __('app.conversations.report.flash.sent'));
    }

    public function destroy(Conversation $conversation, Message $message, MessagingService $messaging): RedirectResponse
    {
        Gate::authorize('delete', $message);

        $messaging->delete($message);

        return redirect()->route('conversations.show', $conversation)->with('success', __('app.conversations.deleted'));
    }
}
