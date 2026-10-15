<?php

namespace App\Http\Resources\Conversations;

use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Message
 */
class MessageSentResource extends JsonResource
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
            'id' => $this->id,
            'conversation_id' => $this->conversation_id,
            'message' => $this->message,
            'created_at' => $this->created_at->toIso8601String(),
            'created_at_human' => $this->created_at->diffForHumans(),
            'unread_count' => $this->unreadCount,
        ];
    }
}
