<?php

namespace App\View\Components\Conversations;

use App\Models\Conversation;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\Component;

class LiveBubble extends Component
{
    public string $classes;

    public string $metaClasses;

    public string $senderName;

    public function __construct(public Conversation $conversation)
    {
        $this->classes = MessageBubble::BASE_CLASSES.' '.MessageBubble::OTHER_CLASSES;
        $this->metaClasses = MessageBubble::META_CLASSES;
        $this->senderName = $conversation->otherParticipant(Auth::user())->username;
    }

    public function render(): View
    {
        return view('components.conversations.live-bubble');
    }
}
