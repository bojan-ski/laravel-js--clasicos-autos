<?php

namespace App\Http\Resources\Conversations;

use App\Models\Message;
use Illuminate\Http\Request;

/**
 * @mixin Message
 */
class MessageSentResource extends MessageResource
{
    public function __construct(Message $message, private int $unreadCount)
    {
        parent::__construct($message);
    }

    /**
     * @return array{id: int, conversation_id: int, message: string, created_at: string, created_at_human: string, unread_count: int}
     */
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'unread_count' => $this->unreadCount,
        ];
    }
}
