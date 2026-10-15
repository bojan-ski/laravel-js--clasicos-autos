<?php

namespace App\Enums;

enum ReportOutcome: string
{
    case Dismissed = 'dismissed';
    case MessageDeleted = 'message_deleted';
    case UserSuspended = 'user_suspended';

    public function label(): string
    {
        return __("app.reports.outcomes.{$this->value}");
    }
}
