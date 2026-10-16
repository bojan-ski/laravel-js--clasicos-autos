<?php

namespace App\View\Components\Conversations;

use App\Models\Conversation;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class OwnBubble extends Component
{
    public string $classes;

    public string $metaClasses;

    public string $senderName;

    public function __construct(public Conversation $conversation)
    {
        $this->classes = MessageBubble::BASE_CLASSES.' '.MessageBubble::OWN_CLASSES.' '.MessageBubble::ARRIVE_CLASSES;
        $this->metaClasses = MessageBubble::META_CLASSES;
        $this->senderName = __('app.conversations.bubble.you');
    }

    public function placeholder(): string
    {
        return LiveBubble::PLACEHOLDER;
    }

    public function deleteAction(): string
    {
        return route('conversations.messages.destroy', [$this->conversation, LiveBubble::PLACEHOLDER]);
    }

    public function deleteDialogId(): string
    {
        return 'delete-message-'.LiveBubble::PLACEHOLDER;
    }

    public function textId(): string
    {
        return 'message-'.LiveBubble::PLACEHOLDER.'-text';
    }

    public function render(): View
    {
        return view('components.conversations.own-bubble');
    }
}
