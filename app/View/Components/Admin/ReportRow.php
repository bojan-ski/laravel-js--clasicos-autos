<?php

namespace App\View\Components\Admin;

use App\Enums\ReportOutcome;
use App\Models\MessageReport;
use App\Support\SuspensionText;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class ReportRow extends Component
{
    /**
     * @param  array{status?: string, page?: int}  $returnQuery
     */
    public function __construct(public MessageReport $report, public array $returnQuery) {}

    public function reportedAt(): string
    {
        return $this->report->created_at->format('d.m.Y H:i');
    }

    public function resolvedAt(): ?string
    {
        return $this->report->resolved_at?->format('d.m.Y H:i');
    }

    public function conversationUrl(): string
    {
        $url = route('conversations.show', $this->report->conversation_id);

        return $this->isMessageDeleted() ? $url : $url.'#message-'.$this->report->message_id;
    }

    public function isMessageDeleted(): bool
    {
        return $this->report->message_id === null;
    }

    public function reportedUserStatus(): ?string
    {
        return $this->report->reportedUser->isSuspended() ? SuspensionText::status($this->report->reportedUser) : null;
    }

    public function statusTone(): string
    {
        return $this->report->isOpen() ? 'status-pending' : 'gold';
    }

    public function resolverName(): string
    {
        return $this->report->resolver === null
            ? __('app.admin.reports.resolved_by_removed')
            : __('app.admin.reports.resolved_by', ['username' => $this->report->resolver->username]);
    }

    public function actionContext(): string
    {
        return __('app.admin.reports.action_context', ['username' => $this->report->reportedUser->username, 'time' => $this->reportedAt()]);
    }

    public function showsActions(): bool
    {
        return $this->report->isOpen();
    }

    public function offersDeleteMessage(): bool
    {
        return $this->report->canBeResolvedAs(ReportOutcome::MessageDeleted);
    }

    public function offersSuspend(): bool
    {
        return $this->showsActions() && ! $this->report->reportedUser->isSuspended();
    }

    public function resolveUrl(): string
    {
        return route('admin.reports.resolve', [$this->report, ...$this->returnQuery]);
    }

    public function deleteDialogId(): string
    {
        return "delete-reported-message-{$this->report->id}";
    }

    public function suspendDialogId(): string
    {
        return "suspend-report-{$this->report->id}";
    }

    /**
     * @return array<string, string>
     */
    public function durationOptions(): array
    {
        return SuspensionText::durationOptions();
    }

    public function render(): View
    {
        return view('components.admin.report-row');
    }
}
