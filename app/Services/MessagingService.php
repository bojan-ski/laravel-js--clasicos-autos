<?php

namespace App\Services;

use App\Models\CarListing;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class MessagingService
{
    public function existingFor(CarListing $listing, User $buyer): ?Conversation
    {
        return Conversation::query()->where($this->triple($listing, $buyer))->first();
    }

    public function start(CarListing $listing, User $buyer, string $text): Message
    {
        return DB::transaction(fn (): Message => $this->append($this->insertOrRead($listing, $buyer), $buyer, $text), attempts: 3);
    }

    public function send(Conversation $conversation, User $sender, string $text): Message
    {
        return DB::transaction(fn (): Message => $this->append($conversation, $sender, $text));
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
