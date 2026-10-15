<?php

namespace Tests\Feature\Conversations;

use App\Models\CarListing;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\MessageReport;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MessageReportSchemaTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Storage::fake('public');
    }

    public function test_the_table_has_the_contract_columns_with_their_nullability(): void
    {
        $columns = collect(Schema::getColumns('message_reports'))->mapWithKeys(fn (array $column): array => [$column['name'] => $column['nullable']])->all();

        $this->assertSame([
            'id' => false,
            'conversation_id' => false,
            'message_id' => true,
            'message_text' => false,
            'reporter_id' => false,
            'reported_user_id' => false,
            'reason' => false,
            'note' => true,
            'status' => false,
            'outcome' => true,
            'resolved_by' => true,
            'resolved_at' => true,
            'created_at' => true,
            'updated_at' => true,
        ], $columns);
        $this->assertTrue(Schema::hasIndex('message_reports', ['message_id', 'reporter_id'], 'unique'));
        $this->assertTrue(Schema::hasIndex('message_reports', ['reported_user_id', 'status']));
        $this->assertTrue(Schema::hasIndex('message_reports', ['status', 'created_at']));
        $this->assertTrue(Schema::hasIndex('message_reports', ['status', 'resolved_at']));
    }

    public function test_a_status_defaults_to_open(): void
    {
        $message = Message::factory()->create();
        $conversation = $message->conversation;

        $id = DB::table('message_reports')->insertGetId([
            'conversation_id' => $conversation->id,
            'message_id' => $message->id,
            'message_text' => $message->message,
            'reporter_id' => $conversation->receiver_id,
            'reported_user_id' => $conversation->sender_id,
            'reason' => 'spam',
        ]);

        $this->assertSame('open', DB::table('message_reports')->where('id', $id)->value('status'));
    }

    public function test_a_second_report_by_the_same_reporter_on_the_same_message_is_refused_by_the_index(): void
    {
        $report = MessageReport::factory()->create();

        $this->expectException(UniqueConstraintViolationException::class);

        MessageReport::factory()->create(['message_id' => $report->message_id, 'reporter_id' => $report->reporter_id]);
    }

    public function test_the_senders_delete_nulls_the_message_and_keeps_an_open_report_with_its_snapshot(): void
    {
        $report = MessageReport::factory()->create();
        $text = $report->message_text;

        $this->actingAs($report->reportedUser)
            ->delete(route('conversations.messages.destroy', [$report->conversation_id, $report->message_id]))
            ->assertRedirect();

        $this->assertSame(['message_id' => null, 'message_text' => $text, 'status' => 'open'], $this->row($report));
    }

    public function test_two_reports_whose_messages_were_deleted_coexist_for_the_same_reporter(): void
    {
        $conversation = Conversation::factory()->create();
        $first = MessageReport::factory()->create(['message_id' => $this->sellerMessage($conversation)->id]);
        $second = MessageReport::factory()->create(['message_id' => $this->sellerMessage($conversation)->id]);

        Message::query()->whereKey([$first->message_id, $second->message_id])->delete();

        $this->assertSame([null, null], DB::table('message_reports')->where('reporter_id', $conversation->sender_id)->pluck('message_id')->all());
    }

    public function test_the_reporters_self_delete_removes_the_report(): void
    {
        $report = MessageReport::factory()->create();

        $this->actingAs($report->reporter)->delete(route('profile.destroy'), ['delete_password' => 'password'])->assertRedirect();

        $this->assertModelMissing($report);
    }

    public function test_an_admin_delete_of_the_reported_user_removes_the_report(): void
    {
        $report = MessageReport::factory()->create();

        $this->actingAs(User::factory()->admin()->create())->delete(route('admin.users.destroy', $report->reported_user_id))->assertRedirect();

        $this->assertModelMissing($report);
    }

    public function test_deleting_the_listing_removes_its_conversations_reports_through_both_paths(): void
    {
        $listing = CarListing::factory()->approved()->create();
        $conversation = Conversation::factory()->for($listing, 'listing')->create();
        $report = MessageReport::factory()->create(['message_id' => $this->sellerMessage($conversation)->id]);
        $survivor = MessageReport::factory()->create();

        $this->actingAs($listing->owner)->delete(route('listings.destroy', $listing))->assertRedirect();

        $this->assertModelMissing($report);
        $this->assertModelExists($survivor);
    }

    public function test_deleting_the_resolver_nulls_resolved_by_and_keeps_the_resolution(): void
    {
        $admin = User::factory()->admin()->create();
        $report = MessageReport::factory()->resolved()->for($admin, 'resolver')->create();

        $admin->delete();

        $this->assertSame([null, 'resolved', 'dismissed'], array_values((array) DB::table('message_reports')->where('id', $report->id)->first(['resolved_by', 'status', 'outcome'])));
    }

    private function sellerMessage(Conversation $conversation): Message
    {
        return Message::factory()->for($conversation)->create(['sender_id' => $conversation->receiver_id]);
    }

    /**
     * @return array{message_id: ?int, message_text: string, status: string}
     */
    private function row(MessageReport $report): array
    {
        return (array) DB::table('message_reports')->where('id', $report->id)->first(['message_id', 'message_text', 'status']);
    }
}
