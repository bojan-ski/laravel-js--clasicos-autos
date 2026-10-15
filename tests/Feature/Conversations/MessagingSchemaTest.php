<?php

namespace Tests\Feature\Conversations;

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MessagingSchemaTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_messages_has_the_unread_index_led_by_the_conversation(): void
    {
        $this->assertTrue(Schema::hasIndex('messages', ['conversation_id', 'read_at', 'sender_id']));
    }

    public function test_conversations_has_the_last_activity_index_for_the_list_order(): void
    {
        $this->assertTrue(Schema::hasIndex('conversations', ['last_message_at']));
    }
}
