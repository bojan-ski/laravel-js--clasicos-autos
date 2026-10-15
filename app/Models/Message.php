<?php

namespace App\Models;

use Database\Factories\MessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['message'])]
class Message extends Model
{
    /** @use HasFactory<MessageFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
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
     * @return BelongsTo<User, $this>
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    /**
     * @return HasMany<MessageReport, $this>
     */
    public function reports(): HasMany
    {
        return $this->hasMany(MessageReport::class);
    }

    public function isSentBy(User $user): bool
    {
        return $this->sender()->is($user);
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function unreadFor(Builder $query, User $viewer): void
    {
        $query->whereNull($query->qualifyColumn('read_at'))
            ->where($query->qualifyColumn('sender_id'), '!=', $viewer->id);
    }
}
