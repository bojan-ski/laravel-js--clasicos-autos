<?php

namespace App\View\Components\Conversations;

use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\Component;

class MessageBubble extends Component
{
    public const string BASE_CLASSES = 'w-fit max-w-110 scroll-mt-16 border px-4 py-3';

    public const string OTHER_CLASSES = 'bg-card border-rule -rotate-[0.4deg]';

    public const string META_CLASSES = 'mt-1 flex flex-wrap items-center gap-x-3 text-[11px] text-muted';

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
