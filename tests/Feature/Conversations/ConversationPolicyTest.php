<?php

namespace Tests\Feature\Conversations;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\MessageReport;
use App\Models\User;
use App\Policies\ConversationPolicy;
use App\Policies\MessagePolicy;
use App\Policies\MessageReportPolicy;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ConversationPolicyTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @return array<string, array{string, int|string}>
     */
    public static function viewOutcomes(): array
    {
        return [
            'buyer' => ['buyer', 'allow'],
            'seller' => ['seller', 'allow'],
            'suspended buyer' => ['suspended buyer', 'allow'],
            'admin' => ['admin', 'allow'],
            'stranger' => ['stranger', 404],
            'suspended stranger' => ['suspended stranger', 404],
        ];
    }

    #[DataProvider('viewOutcomes')]
    public function test_participants_and_admins_view_a_conversation_and_strangers_get_404(string $viewerKind, int|string $expected): void
    {
        [$conversation, $viewer] = $this->arrange($viewerKind);

        $response = Gate::forUser($viewer)->inspect('view', $conversation);

        $this->assertSame($expected, $this->outcome($response));
    }

    /**
     * @return array<string, array{string, int|string}>
     */
    public static function replyOutcomes(): array
    {
        return [
            'buyer' => ['buyer', 'allow'],
            'seller' => ['seller', 'allow'],
            'suspended buyer' => ['suspended buyer', 403],
            'admin' => ['admin', 403],
            'stranger' => ['stranger', 404],
            'suspended stranger' => ['suspended stranger', 404],
        ];
    }

    #[DataProvider('replyOutcomes')]
    public function test_only_active_participants_reply(string $viewerKind, int|string $expected): void
    {
        [$conversation, $viewer] = $this->arrange($viewerKind);

        $response = Gate::forUser($viewer)->inspect('reply', $conversation);

        $this->assertSame($expected, $this->outcome($response));
    }

    /**
     * @return array<string, array{string, int|string}>
     */
    public static function deleteOutcomes(): array
    {
        return [
            'the buyer who sent it' => ['buyer', 'allow'],
            'the seller who did not send it' => ['seller', 403],
            'an admin' => ['admin', 'allow'],
            'a stranger' => ['stranger', 404],
        ];
    }

    #[DataProvider('deleteOutcomes')]
    public function test_the_sender_or_an_admin_deletes_a_message(string $viewerKind, int|string $expected): void
    {
        [$conversation, $viewer] = $this->arrange($viewerKind);
        $message = Message::factory()->for($conversation)->create();

        $response = Gate::forUser($viewer)->inspect('delete', $message);

        $this->assertSame($expected, $this->outcome($response));
    }

    public function test_a_suspended_sender_still_deletes_their_own_message(): void
    {
        [$conversation, $viewer] = $this->arrange('suspended buyer');
        $message = Message::factory()->for($conversation)->create();

        $this->assertTrue(Gate::forUser($viewer)->allows('delete', $message));
    }

    /**
     * @return array<string, array{string, int|string}>
     */
    public static function reportOutcomes(): array
    {
        return [
            'the buyer on the seller\'s message' => ['buyer', 'allow'],
            'the seller on their own message' => ['seller', 403],
            'a suspended buyer' => ['suspended buyer', 403],
            'an admin' => ['admin', 403],
            'a stranger' => ['stranger', 404],
            'a suspended stranger' => ['suspended stranger', 404],
        ];
    }

    #[DataProvider('reportOutcomes')]
    public function test_only_an_active_participant_reports_the_other_partys_message(string $viewerKind, int|string $expected): void
    {
        [$conversation, $viewer] = $this->arrange($viewerKind);
        $message = Message::factory()->for($conversation)->create(['sender_id' => $conversation->receiver_id]);

        $response = Gate::forUser($viewer)->inspect('report', $message);

        $this->assertSame($expected, $this->outcome($response));
    }

    public function test_a_report_already_made_does_not_deny_reporting(): void
    {
        [$conversation, $buyer] = $this->arrange('buyer');
        $message = Message::factory()->for($conversation)->create(['sender_id' => $conversation->receiver_id]);
        MessageReport::factory()->resolved()->create(['message_id' => $message->id]);

        $this->assertTrue(Gate::forUser($buyer)->allows('report', $message));
    }

    /**
     * @return array<string, array{string, int|string}>
     */
    public static function resolveOutcomes(): array
    {
        return [
            'admin' => ['admin', 'allow'],
            'the reporter' => ['buyer', 403],
            'the reported user' => ['seller', 403],
            'a stranger' => ['stranger', 403],
        ];
    }

    #[DataProvider('resolveOutcomes')]
    public function test_only_an_admin_resolves_a_report(string $viewerKind, int|string $expected): void
    {
        [$conversation, $viewer] = $this->arrange($viewerKind);
        $message = Message::factory()->for($conversation)->create(['sender_id' => $conversation->receiver_id]);
        $report = MessageReport::factory()->create(['message_id' => $message->id]);

        $response = Gate::forUser($viewer)->inspect('resolve', $report);

        $this->assertSame($expected, $this->outcome($response));
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function messagingGate(): array
    {
        return [
            'active app user' => ['stranger', true],
            'suspended app user' => ['suspended stranger', false],
            'admin' => ['admin', false],
        ];
    }

    #[DataProvider('messagingGate')]
    public function test_the_messaging_gate_admits_only_active_app_users(string $viewerKind, bool $expected): void
    {
        [, $viewer] = $this->arrange($viewerKind);

        $this->assertSame($expected, Gate::forUser($viewer)->allows('access-messaging'));
    }

    public function test_the_three_policies_are_auto_discovered(): void
    {
        $this->assertInstanceOf(ConversationPolicy::class, Gate::getPolicyFor(Conversation::class));
        $this->assertInstanceOf(MessagePolicy::class, Gate::getPolicyFor(Message::class));
        $this->assertInstanceOf(MessageReportPolicy::class, Gate::getPolicyFor(MessageReport::class));
    }

    /**
     * @return array{Conversation, User}
     */
    private function arrange(string $viewerKind): array
    {
        $buyer = $viewerKind === 'suspended buyer' ? User::factory()->suspended()->create() : User::factory()->create();
        $conversation = Conversation::factory()->for($buyer, 'sender')->create();

        $viewer = match ($viewerKind) {
            'buyer', 'suspended buyer' => $buyer,
            'seller' => $conversation->receiver,
            'admin' => User::factory()->admin()->create(),
            'stranger' => User::factory()->create(),
            'suspended stranger' => User::factory()->suspended()->create(),
        };

        return [$conversation, $viewer];
    }

    private function outcome(Response $response): int|string
    {
        return $response->allowed() ? 'allow' : ($response->status() ?? 403);
    }
}
