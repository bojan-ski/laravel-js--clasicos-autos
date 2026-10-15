<?php

namespace App\Enums;

enum ReportStatus: string
{
    case Open = 'open';
    case Resolved = 'resolved';

    public function label(): string
    {
        return __("app.reports.status.{$this->value}");
    }

    public function emptyMessage(): string
    {
        return __("app.admin.reports.empty.{$this->value}");
    }
}
