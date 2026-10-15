<?php

namespace App\Events;

use App\Http\Resources\Conversations\MessageSentResource;
use App\Models\Message;
use App\Models\User;
use App\Services\MessagingService;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\SerializesModels;

#[Tries(3)]
#[Timeout(15)]
#[Backoff(5)]
class MessageSent implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Message $message, public User $recipient) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('user.'.$this->recipient->getKey());
    }

    public function broadcastAs(): string
    {
        return 'message.sent';
    }

    /**
     * @return array{id: int, conversation_id: int, message: string, created_at: string, created_at_human: string, unread_count: int}
     */
    public function broadcastWith(): array
    {
        $unreadCount = app(MessagingService::class)->unreadCountFor($this->recipient);

        return MessageSentResource::make($this->message, $unreadCount)->resolve();
    }
}
