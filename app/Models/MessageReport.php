<?php

namespace App\Models;

use App\Enums\ReportOutcome;
use App\Enums\ReportReason;
use App\Enums\ReportStatus;
use Database\Factories\MessageReportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['reason', 'note'])]
class MessageReport extends Model
{
    /** @use HasFactory<MessageReportFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'reason' => ReportReason::class,
            'status' => ReportStatus::class,
            'outcome' => ReportOutcome::class,
            'resolved_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Conversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * @return BelongsTo<Message, $this>
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reportedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function isOpen(): bool
    {
        return $this->status === ReportStatus::Open;
    }

    public function canBeResolvedAs(ReportOutcome $outcome): bool
    {
        if (! $this->isOpen()) {
            return false;
        }

        return $outcome !== ReportOutcome::MessageDeleted || $this->message_id !== null;
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function open(Builder $query): void
    {
        $query->where($query->qualifyColumn('status'), ReportStatus::Open);
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function against(Builder $query, User $user): void
    {
        $query->where($query->qualifyColumn('reported_user_id'), $user->id);
    }
}
