<?php

namespace App\Http\Requests\Conversations;

use App\Models\Conversation;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\Gate;

class StoreMessageRequest extends MessageRequest
{
    public function authorize(): Response
    {
        return Gate::inspect('reply', $this->conversation());
    }

    protected function getRedirectUrl(): string
    {
        return route('conversations.show', $this->conversation());
    }

    private function conversation(): Conversation
    {
        return $this->route('conversation');
    }
}
