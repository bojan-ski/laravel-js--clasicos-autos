<?php

namespace App\Services;

use App\Enums\ReportOutcome;
use App\Enums\ReportReason;
use App\Enums\ReportStatus;
use App\Exceptions\InvalidReportTransitionException;
use App\Exceptions\MessageAlreadyReportedException;
use App\Models\Message;
use App\Models\MessageReport;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class MessageReportService
{
    public function __construct(private MessagingService $messaging, private SuspensionService $suspensions) {}

    public function report(Message $message, User $reporter, ReportReason $reason, ?string $note): MessageReport
    {
        $report = new MessageReport(['reason' => $reason, 'note' => $note]);
        $report->conversation_id = $message->conversation_id;
        $report->message_id = $message->id;
        $report->message_text = $message->message;
        $report->reporter_id = $reporter->id;
        $report->reported_user_id = $message->sender_id;
        $report->status = ReportStatus::Open;

        try {
            DB::transaction(function () use ($report, $message): void {
                User::query()->whereKey($message->sender_id)->sharedLock()->first();
                Message::query()->whereKey($message->id)->sharedLock()->firstOrFail();

                $report->save();
            }, attempts: 3);
        } catch (UniqueConstraintViolationException $exception) {
            throw new MessageAlreadyReportedException("Message {$message->id} is already reported by user {$reporter->id}.", previous: $exception);
        }

        return $report;
    }

    /**
     * @return LengthAwarePaginator<int, MessageReport>
     */
    public function paginate(ReportStatus $status): LengthAwarePaginator
    {
        $query = MessageReport::query()
            ->where('status', $status)
            ->with(['reporter', 'reportedUser', 'resolver']);

        if ($status === ReportStatus::Open) {
            $query->orderBy('created_at')->orderBy('id');
        } else {
            $query->orderByDesc('resolved_at')->orderByDesc('id');
        }

        return $query->paginate(config('listings.per_page.admin'))->withQueryString();
    }

    public function openCount(): int
    {
        return MessageReport::query()->open()->count();
    }

    public function dismiss(MessageReport $report, User $admin): void
    {
        DB::transaction(function () use ($report, $admin): void {
            $locked = $this->lockFor($report, ReportOutcome::Dismissed);

            $this->resolve($locked, ReportOutcome::Dismissed, $admin);
        });
    }

    public function deleteMessage(MessageReport $report, User $admin): void
    {
        DB::transaction(function () use ($report, $admin): void {
            $message = $report->message_id === null ? null : Message::query()->lockForUpdate()->find($report->message_id);
            $locked = $this->lockFor($report, ReportOutcome::MessageDeleted);

            $this->resolve($locked, ReportOutcome::MessageDeleted, $admin);
            $this->messaging->delete($message);
        }, attempts: 3);
    }

    public function suspendAuthor(MessageReport $report, User $admin, string $reason, ?int $days): void
    {
        DB::transaction(function () use ($report, $admin, $reason, $days): void {
            $author = User::query()->lockForUpdate()->findOrFail($report->reported_user_id);
            $this->lockFor($report, ReportOutcome::UserSuspended);

            $this->suspensions->suspend($author, $reason, $days, $admin);
        }, attempts: 3);
    }

    private function lockFor(MessageReport $report, ReportOutcome $outcome): MessageReport
    {
        $locked = MessageReport::query()->lockForUpdate()->findOrFail($report->getKey());

        if (! $locked->canBeResolvedAs($outcome)) {
            throw new InvalidReportTransitionException(
                "Report {$locked->getKey()} cannot be resolved as {$outcome->value} from status {$locked->status->value}."
            );
        }

        return $locked;
    }

    private function resolve(MessageReport $report, ReportOutcome $outcome, User $admin): void
    {
        $report->status = ReportStatus::Resolved;
        $report->outcome = $outcome;
        $report->resolved_by = $admin->id;
        $report->resolved_at = now();
        $report->save();
    }
}
