<?php

namespace Tests\Feature\Contact;

use App\Models\ContactMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ContactMessagePolicyTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @return array<string, array{string, bool, bool}>
     */
    public static function actors(): array
    {
        return [
            'active app user' => ['active', true, false],
            'suspended app user' => ['suspended', false, false],
            'admin' => ['admin', false, true],
        ];
    }

    #[DataProvider('actors')]
    public function test_only_active_app_users_send_and_only_admins_change_the_status(string $kind, bool $creates, bool $updatesStatus): void
    {
        $actor = match ($kind) {
            'active' => User::factory()->create(),
            'suspended' => User::factory()->suspended()->create(),
            'admin' => User::factory()->admin()->create(),
        };
        $message = ContactMessage::factory()->create();

        $this->assertSame($creates, Gate::forUser($actor)->allows('create', ContactMessage::class));
        $this->assertSame($updatesStatus, Gate::forUser($actor)->allows('updateStatus', $message));
    }

    public function test_the_sender_cannot_change_their_own_message_status(): void
    {
        $message = ContactMessage::factory()->create();

        $this->assertFalse(Gate::forUser($message->user)->allows('updateStatus', $message));
    }
}
