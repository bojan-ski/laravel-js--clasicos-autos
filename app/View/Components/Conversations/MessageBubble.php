<?php

namespace App\View\Components\Conversations;

use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\Component;

class MessageBubble extends Component
{
    public bool $own;

    public bool $showsSender;

    public string $senderName;

    public function __construct(public Message $message, public Conversation $conversation)
    {
        $viewer = Auth::user();
        $isParticipant = $viewer !== null && $conversation->hasParticipant($viewer);
        $author = $message->isSentBy($conversation->sender) ? $conversation->sender : $conversation->receiver;

        $this->own = $isParticipant ? $message->isSentBy($viewer) : $message->isSentBy($conversation->receiver);
        $this->showsSender = ! $isParticipant;
        $this->senderName = $isParticipant && $this->own ? __('app.conversations.bubble.you') : $author->username;
    }

    public function time(): string
    {
        return $this->message->created_at->diffForHumans();
    }

    public function datetime(): string
    {
        return $this->message->created_at->toIso8601String();
    }

    public function deleteAction(): string
    {
        return route('conversations.messages.destroy', [$this->conversation, $this->message]);
    }

    public function render(): View
    {
        return view('components.conversations.message-bubble');
    }
}
