<?php

namespace App\Enums;

enum ReportReason: string
{
    case Spam = 'spam';
    case Abusive = 'abusive';
    case Scam = 'scam';
    case Other = 'other';

    public function label(): string
    {
        return __("app.reports.reasons.{$this->value}");
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $reason) {
            $options[$reason->value] = $reason->label();
        }

        return $options;
    }
}
