<?php

namespace App\View\Components\Admin;

use App\Enums\ContactMessageStatus;
use App\Models\ContactMessage;
use App\Support\SuspensionText;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class FeedbackRow extends Component
{
    /**
     * @param  array{status?: string, page?: int}  $returnQuery
     */
    public function __construct(public ContactMessage $contactMessage, public array $returnQuery) {}

    public function receivedAt(): string
    {
        return $this->contactMessage->created_at->format('d.m.Y H:i');
    }

    public function receivedIso(): string
    {
        return $this->contactMessage->created_at->toIso8601String();
    }

    public function handledAt(): ?string
    {
        return $this->contactMessage->handled_at?->format('d.m.Y H:i');
    }

    public function handledIso(): ?string
    {
        return $this->contactMessage->handled_at?->toIso8601String();
    }

    public function senderStatus(): ?string
    {
        return $this->contactMessage->user->isSuspended() ? SuspensionText::status($this->contactMessage->user) : null;
    }

    public function mailto(): string
    {
        return 'mailto:'.$this->contactMessage->user->email;
    }

    public function isNew(): bool
    {
        return $this->contactMessage->isNew();
    }

    public function statusTone(): string
    {
        return $this->isNew() ? 'status-pending' : 'gold';
    }

    public function handlerName(): ?string
    {
        if ($this->isNew()) {
            return null;
        }

        return $this->contactMessage->handler === null
            ? __('app.admin.feedback.handled_by_removed')
            : __('app.admin.feedback.handled_by', ['username' => $this->contactMessage->handler->username]);
    }

    public function actionTarget(): ContactMessageStatus
    {
        return $this->isNew() ? ContactMessageStatus::Handled : ContactMessageStatus::New;
    }

    public function actionLabel(): string
    {
        return $this->isNew() ? __('app.admin.feedback.actions.mark_handled') : __('app.admin.feedback.actions.mark_new');
    }

    public function actionVariant(): string
    {
        return $this->isNew() ? 'primary' : 'secondary';
    }

    public function actionContext(): string
    {
        return __('app.admin.feedback.action_context', ['username' => $this->contactMessage->user->username, 'time' => $this->receivedAt()]);
    }

    public function actionUrl(): string
    {
        return route('admin.feedback.status', [$this->contactMessage, ...$this->returnQuery]);
    }

    public function render(): View
    {
        return view('components.admin.feedback-row');
    }
}
