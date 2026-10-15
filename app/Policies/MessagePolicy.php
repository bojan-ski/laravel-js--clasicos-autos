<?php

namespace App\Policies;

use App\Models\Message;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class MessagePolicy
{
    public function __construct(private ConversationPolicy $conversations) {}

    public function delete(User $user, Message $message): Response
    {
        if ($this->conversations->view($user, $message->conversation)->denied()) {
            return Response::denyAsNotFound();
        }

        return $message->isSentBy($user) || $user->isAdmin() ? Response::allow() : Response::deny();
    }
}
