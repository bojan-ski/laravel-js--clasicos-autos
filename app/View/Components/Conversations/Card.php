<?php

namespace App\View\Components\Conversations;

use App\Models\Conversation;
use Carbon\CarbonInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\Component;

class Card extends Component
{
    public bool $isParticipant;

    public string $heading;

    public function __construct(public Conversation $conversation)
    {
        $viewer = Auth::user();

        $this->isParticipant = $viewer !== null && $conversation->hasParticipant($viewer);
        $this->heading = $this->isParticipant
            ? $conversation->otherParticipant($viewer)->username
            : __('app.conversations.participants', ['buyer' => $conversation->sender->username, 'seller' => $conversation->receiver->username]);
    }

    public function url(): string
    {
        return route('conversations.show', $this->conversation);
    }

    public function thumbUrl(): ?string
    {
        return $this->conversation->listing->primaryImage?->thumbUrl();
    }

    public function lastMessage(): ?string
    {
        return $this->conversation->latestMessage?->message;
    }

    public function lastMessageId(): int
    {
        return $this->conversation->latestMessage?->id ?? 0;
    }

    public function timeIso(): ?string
    {
        return $this->time()?->toIso8601String();
    }

    public function timeHuman(): ?string
    {
        return $this->time()?->diffForHumans();
    }

    public function unreadCount(): int
    {
        return $this->isParticipant ? (int) $this->conversation->unread_count : 0;
    }

    public function isUnread(): bool
    {
        return $this->unreadCount() > 0;
    }

    public function unreadId(): string
    {
        return "conversation-{$this->conversation->id}-unread";
    }

    public function countTemplate(int $count): string
    {
        return trans_choice('app.conversations.index.stamp_count', $count, ['count' => ':count']);
    }

    public function render(): View
    {
        return view('components.conversations.card');
    }

    private function time(): ?CarbonInterface
    {
        return $this->conversation->latestMessage?->created_at ?? $this->conversation->last_message_at;
    }
}
