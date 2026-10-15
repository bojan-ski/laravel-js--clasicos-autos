<?php

namespace App\View\Components\Admin;

use App\Enums\ReportStatus;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class ReportStatusChips extends Component
{
    public function __construct(public ReportStatus $status) {}

    /**
     * @return list<array{label: string, url: string, active: bool}>
     */
    public function chips(): array
    {
        $chips = [];

        foreach (ReportStatus::cases() as $status) {
            $chips[] = [
                'label' => __("app.admin.reports.chips.{$status->value}"),
                'url' => route('admin.reports', $status === ReportStatus::Open ? [] : ['status' => $status->value]),
                'active' => $status === $this->status,
            ];
        }

        return $chips;
    }

    public function render(): View
    {
        return view('components.admin.report-status-chips');
    }
}
