<?php

namespace App\Policies;

use App\Models\MessageReport;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class MessageReportPolicy
{
    public function resolve(User $user, MessageReport $report): Response
    {
        return $user->isAdmin() ? Response::allow() : Response::deny();
    }
}
