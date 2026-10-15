<?php

namespace App\Policies;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ConversationPolicy
{
    public function view(User $user, Conversation $conversation): Response
    {
        return $conversation->hasParticipant($user) || $user->isAdmin() ? Response::allow() : Response::denyAsNotFound();
    }

    public function reply(User $user, Conversation $conversation): Response
    {
        if ($this->view($user, $conversation)->denied()) {
            return Response::denyAsNotFound();
        }

        return $conversation->hasParticipant($user) && $user->isActiveAppUser() ? Response::allow() : Response::deny();
    }
}
