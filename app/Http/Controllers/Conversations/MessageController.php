<?php

namespace App\Http\Controllers\Conversations;

use App\Http\Controllers\Controller;
use App\Http\Requests\Conversations\StoreMessageRequest;
use App\Models\Conversation;
use App\Models\Message;
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

    public function destroy(Conversation $conversation, Message $message, MessagingService $messaging): RedirectResponse
    {
        Gate::authorize('delete', $message);

        $messaging->delete($message);

        return redirect()->route('conversations.show', $conversation)->with('success', __('app.conversations.deleted'));
    }
}
