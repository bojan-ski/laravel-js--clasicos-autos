<?php

namespace App\Services;

use App\Events\MessageSent;
use App\Models\CarListing;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class MessagingService
{
    public function existingFor(CarListing $listing, User $buyer): ?Conversation
    {
        return Conversation::query()->where($this->triple($listing, $buyer))->first();
    }

    public function start(CarListing $listing, User $buyer, string $text): Message
    {
        return DB::transaction(function () use ($listing, $buyer, $text): Message {
            $message = $this->append($this->insertOrRead($listing, $buyer), $buyer, $text);

            MessageSent::dispatch($message, $listing->loadMissing('owner')->owner);

            return $message;
        }, attempts: 3);
    }

    public function send(Conversation $conversation, User $sender, string $text): Message
    {
        return DB::transaction(function () use ($conversation, $sender, $text): Message {
            $message = $this->append($conversation, $sender, $text);

            MessageSent::dispatch($message, $conversation->loadMissing(['sender', 'receiver'])->otherParticipant($sender));

            return $message;
        });
    }

    public function delete(Message $message): void
    {
        $message->delete();
    }

    public function markRead(Conversation $conversation, User $viewer): int
    {
        if (! $conversation->hasParticipant($viewer)) {
            return 0;
        }

        return Message::query()
            ->where('conversation_id', $conversation->id)
            ->where('sender_id', '!=', $viewer->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    public function thread(Conversation $conversation): Conversation
    {
        return $conversation->load(['listing.owner', 'listing.primaryImage', 'sender', 'receiver', 'messages']);
    }

    public function forNewConversation(CarListing $listing): CarListing
    {
        return $listing->loadMissing(['owner', 'primaryImage']);
    }

    /**
     * @return LengthAwarePaginator<int, Conversation>
     */
    public function conversationsFor(User $viewer): LengthAwarePaginator
    {
        return Conversation::query()
            ->unless($viewer->isAdmin(), fn (Builder $query) => $query
                ->involving($viewer)
                ->withCount(['messages as unread_count' => fn (Builder $messages) => $messages
                    ->whereNull($messages->qualifyColumn('read_at'))
                    ->where($messages->qualifyColumn('sender_id'), '!=', $viewer->id)]))
            ->with(['listing.owner', 'listing.primaryImage', 'sender', 'receiver', 'latestMessage'])
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->paginate(config('listings.per_page.conversations'));
    }

    public function unreadCountFor(User $viewer): int
    {
        if ($viewer->isAdmin()) {
            return 0;
        }

        $messages = Message::query();

        return $messages
            ->whereNull($messages->qualifyColumn('read_at'))
            ->where($messages->qualifyColumn('sender_id'), '!=', $viewer->id)
            ->whereHas('conversation', fn (Builder $conversation) => $conversation->involving($viewer))
            ->count();
    }

    private function insertOrRead(CarListing $listing, User $buyer): Conversation
    {
        $conversation = new Conversation;
        $conversation->listing_id = $listing->id;
        $conversation->sender_id = $buyer->id;
        $conversation->receiver_id = $listing->user_id;

        try {
            DB::transaction(fn () => $conversation->save());

            return $conversation;
        } catch (UniqueConstraintViolationException $exception) {
            return Conversation::query()->useWritePdo()->where($this->triple($listing, $buyer))->first() ?? throw $exception;
        }
    }

    private function append(Conversation $conversation, User $sender, string $text): Message
    {
        Conversation::query()->whereKey($conversation->id)->lockForUpdate()->first();

        $message = new Message(['message' => $text]);
        $message->conversation()->associate($conversation);
        $message->sender()->associate($sender);
        $message->save();

        $conversation->last_message_at = $message->created_at;
        $conversation->save();

        return $message;
    }

    /**
     * @return array{sender_id: int, receiver_id: int, listing_id: int}
     */
    private function triple(CarListing $listing, User $buyer): array
    {
        return ['sender_id' => $buyer->id, 'receiver_id' => $listing->user_id, 'listing_id' => $listing->id];
    }
}
