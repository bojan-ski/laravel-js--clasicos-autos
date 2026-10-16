<?php

namespace App\Enums;

enum ContactMessageStatus: string
{
    case New = 'new';
    case Handled = 'handled';

    public function label(): string
    {
        return __("app.feedback.status.{$this->value}");
    }

    public function emptyMessage(): string
    {
        return __("app.admin.feedback.empty.{$this->value}");
    }
}
