<?php

namespace App\View\Components\Admin;

use App\Enums\ContactMessageStatus;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class FeedbackStatusChips extends Component
{
    public function __construct(public ContactMessageStatus $status) {}

    /**
     * @return list<array{label: string, url: string, active: bool}>
     */
    public function chips(): array
    {
        $chips = [];

        foreach (ContactMessageStatus::cases() as $status) {
            $chips[] = [
                'label' => __("app.admin.feedback.chips.{$status->value}"),
                'url' => route('admin.feedback', $status === ContactMessageStatus::New ? [] : ['status' => $status->value]),
                'active' => $status === $this->status,
            ];
        }

        return $chips;
    }

    public function render(): View
    {
        return view('components.admin.feedback-status-chips');
    }
}
