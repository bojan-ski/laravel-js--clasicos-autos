<?php

namespace Tests\Feature\Contact;

use App\Enums\ContactMessageStatus;
use App\Models\ContactMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ContactMessageSchemaTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_contact_messages_has_exactly_the_contract_columns(): void
    {
        $columns = collect(Schema::getColumns('contact_messages'))->pluck('type', 'name')->all();

        $this->assertSame([
            'id' => 'bigint unsigned',
            'user_id' => 'bigint unsigned',
            'subject' => 'varchar(120)',
            'message' => 'text',
            'status' => 'varchar(255)',
            'handled_at' => 'timestamp',
            'handled_by' => 'bigint unsigned',
            'created_at' => 'timestamp',
            'updated_at' => 'timestamp',
        ], $columns);
    }

    public function test_status_defaults_to_new_and_leads_one_index_per_view_ordering(): void
    {
        $status = collect(Schema::getColumns('contact_messages'))->firstWhere('name', 'status');
        $statusIndexes = collect(Schema::getIndexes('contact_messages'))
            ->filter(fn (array $index): bool => $index['columns'][0] === 'status')
            ->pluck('columns')
            ->sort()
            ->values()
            ->all();

        $this->assertSame('new', $status['default']);
        $this->assertSame([['status', 'created_at'], ['status', 'handled_at']], $statusIndexes);
    }

    public function test_the_sender_cascades_and_the_handler_is_nulled(): void
    {
        $keys = collect(Schema::getForeignKeys('contact_messages'))
            ->mapWithKeys(fn (array $key): array => [$key['columns'][0] => [$key['foreign_table'], $key['on_delete']]])
            ->all();

        $this->assertSame(['handled_by' => ['users', 'set null'], 'user_id' => ['users', 'cascade']], collect($keys)->sortKeys()->all());
    }

    public function test_a_self_deleted_sender_takes_their_messages_with_them(): void
    {
        $sender = User::factory()->create();
        ContactMessage::factory()->count(2)->for($sender)->create();
        $kept = ContactMessage::factory()->create();

        $this->actingAs($sender)->delete(route('profile.destroy'), ['delete_password' => 'password'])->assertSessionHasNoErrors();

        $this->assertModelMissing($sender);
        $this->assertSame([$kept->id], ContactMessage::query()->pluck('id')->all());
    }

    public function test_an_admin_deleted_sender_takes_their_messages_with_them(): void
    {
        $sender = User::factory()->create();
        ContactMessage::factory()->handled()->for($sender)->create();
        $kept = ContactMessage::factory()->create();

        $this->actingAs(User::factory()->admin()->create())->delete(route('admin.users.destroy', $sender))->assertSessionHas('success');

        $this->assertModelMissing($sender);
        $this->assertSame([$kept->id], ContactMessage::query()->pluck('id')->all());
    }

    public function test_deleting_the_handling_admin_nulls_handled_by_and_keeps_the_message_handled(): void
    {
        $admin = User::factory()->admin()->create();
        $message = ContactMessage::factory()->handled()->for($admin, 'handler')->create();

        $admin->delete();

        $message->refresh();
        $this->assertSame([ContactMessageStatus::Handled, null], [$message->status, $message->handled_by]);
        $this->assertNotNull($message->handled_at);
    }
}
