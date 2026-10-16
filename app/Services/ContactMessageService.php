<?php

namespace App\Services;

use App\Enums\ContactMessageStatus;
use App\Exceptions\InvalidContactMessageTransitionException;
use App\Models\ContactMessage;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ContactMessageService
{
    public function send(User $sender, string $subject, string $message): ContactMessage
    {
        $contactMessage = new ContactMessage(['subject' => $subject, 'message' => $message]);
        $contactMessage->user_id = $sender->id;
        $contactMessage->status = ContactMessageStatus::New;
        $contactMessage->save();

        return $contactMessage;
    }

    /**
     * @return LengthAwarePaginator<int, ContactMessage>
     */
    public function paginate(ContactMessageStatus $status): LengthAwarePaginator
    {
        $query = ContactMessage::query()
            ->inStatus($status)
            ->with(['user', 'handler']);

        if ($status === ContactMessageStatus::New) {
            $query->orderByDesc('created_at')->orderByDesc('id');
        } else {
            $query->orderByDesc('handled_at')->orderByDesc('id');
        }

        return $query->paginate(config('listings.per_page.admin'))->withQueryString();
    }

    public function newCount(): int
    {
        return ContactMessage::query()->inStatus(ContactMessageStatus::New)->count();
    }

    public function markHandled(ContactMessage $contactMessage, User $admin): void
    {
        DB::transaction(function () use ($contactMessage, $admin): void {
            $locked = $this->lockFor($contactMessage, ContactMessageStatus::Handled);

            $locked->status = ContactMessageStatus::Handled;
            $locked->handled_at = now();
            $locked->handled_by = $admin->id;
            $locked->save();
        });
    }

    public function markNew(ContactMessage $contactMessage): void
    {
        DB::transaction(function () use ($contactMessage): void {
            $locked = $this->lockFor($contactMessage, ContactMessageStatus::New);

            $locked->status = ContactMessageStatus::New;
            $locked->handled_at = null;
            $locked->handled_by = null;
            $locked->save();
        });
    }

    private function lockFor(ContactMessage $contactMessage, ContactMessageStatus $target): ContactMessage
    {
        $locked = ContactMessage::query()->lockForUpdate()->findOrFail($contactMessage->getKey());

        $allowed = $target === ContactMessageStatus::Handled ? $locked->canBeMarkedHandled() : $locked->canBeMarkedNew();

        if (! $allowed) {
            throw new InvalidContactMessageTransitionException(
                "Contact message {$locked->getKey()} cannot move to {$target->value} from status {$locked->status->value}."
            );
        }

        return $locked;
    }
}
