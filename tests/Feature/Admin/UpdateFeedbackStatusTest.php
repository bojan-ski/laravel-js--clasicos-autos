<?php

namespace Tests\Feature\Admin;

use App\Enums\ContactMessageStatus;
use App\Exceptions\InvalidContactMessageTransitionException;
use App\Models\ContactMessage;
use App\Models\User;
use App\Services\ContactMessageService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class UpdateFeedbackStatusTest extends TestCase
{
    use LazilyRefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->freezeSecond();
        $this->admin = User::factory()->admin()->create();
    }

    public function test_mark_handled_writes_the_status_time_and_admin_together_and_returns_to_the_view(): void
    {
        $message = ContactMessage::factory()->create();

        $this->update($message, 'handled', ['status' => 'new', 'page' => 2])
            ->assertRedirect(route('admin.feedback', ['status' => 'new', 'page' => 2]))
            ->assertSessionHas('success', 'The message is marked as handled.');

        $message->refresh();
        $this->assertSame([ContactMessageStatus::Handled, $this->admin->id], [$message->status, $message->handled_by]);
        $this->assertTrue($message->handled_at->equalTo(now()));
    }

    public function test_mark_as_new_clears_the_handling_time_and_admin(): void
    {
        $message = ContactMessage::factory()->handled()->for($this->admin, 'handler')->create();

        $this->update($message, 'new', ['status' => 'handled'])
            ->assertRedirect(route('admin.feedback', ['status' => 'handled']))
            ->assertSessionHas('success', 'The message is marked as new.');

        $message->refresh();
        $this->assertSame([ContactMessageStatus::New, null, null], [$message->status, $message->handled_at, $message->handled_by]);
    }

    public function test_marking_a_handled_message_handled_again_is_refused_and_changes_nothing(): void
    {
        $other = User::factory()->admin()->create();
        $message = ContactMessage::factory()->handled()->for($other, 'handler')->create(['handled_at' => now()->subDay()]);

        $this->update($message, 'handled')
            ->assertRedirect(route('admin.feedback'))
            ->assertSessionHas('error', 'This message has already changed. Nothing was updated.')
            ->assertSessionMissing('success');

        $message->refresh();
        $this->assertSame([ContactMessageStatus::Handled, $other->id], [$message->status, $message->handled_by]);
        $this->assertTrue($message->handled_at->equalTo(now()->subDay()));
    }

    public function test_marking_a_new_message_new_is_refused_and_changes_nothing(): void
    {
        $message = ContactMessage::factory()->create();
        $updatedAt = $message->updated_at;

        $this->travel(5)->minutes();
        $this->update($message, 'new')->assertSessionHas('error');

        $message->refresh();
        $this->assertSame([ContactMessageStatus::New, null, null], [$message->status, $message->handled_at, $message->handled_by]);
        $this->assertTrue($message->updated_at->equalTo($updatedAt));
    }

    public function test_a_replayed_post_never_flips_the_message_back(): void
    {
        $message = ContactMessage::factory()->create();

        $this->update($message, 'handled')->assertSessionHas('success');
        $this->update($message, 'handled')->assertSessionHas('error');

        $this->assertSame(ContactMessageStatus::Handled, $message->refresh()->status);
    }

    public function test_the_guard_reads_the_locked_row_not_a_stale_instance(): void
    {
        $other = User::factory()->admin()->create();
        $stale = ContactMessage::factory()->create();
        ContactMessage::query()->whereKey($stale->id)->update(['status' => 'handled', 'handled_at' => now()->subHour(), 'handled_by' => $other->id]);

        try {
            app(ContactMessageService::class)->markHandled($stale, $this->admin);
        } catch (InvalidContactMessageTransitionException $exception) {
        }

        $this->assertInstanceOf(InvalidContactMessageTransitionException::class, $exception ?? null);
        $fresh = $stale->fresh();
        $this->assertSame($other->id, $fresh->handled_by);
        $this->assertTrue($fresh->handled_at->equalTo(now()->subHour()));
    }

    public function test_the_view_filter_in_the_query_string_never_shadows_the_target_in_the_body(): void
    {
        $message = ContactMessage::factory()->create();

        $this->update($message, 'handled', ['status' => 'new'])->assertSessionHas('success');

        $this->assertSame(ContactMessageStatus::Handled, $message->refresh()->status);
    }

    public function test_hostile_view_values_and_a_hostile_referer_are_dropped(): void
    {
        $message = ContactMessage::factory()->create();

        $this->actingAs($this->admin)->call(
            'PATCH',
            route('admin.feedback.status', [$message, 'status' => 'archived', 'page' => '-3']),
            ['target_status' => 'handled'],
            server: $this->transformHeadersToServerVars(['Referer' => 'https://evil.example/']),
        )->assertRedirect(route('admin.feedback'))->assertSessionHas('success');

        $this->update(ContactMessage::factory()->create(), 'handled', ['status' => 'handled', 'page' => 'abc'])
            ->assertRedirect(route('admin.feedback', ['status' => 'handled']));
    }

    /**
     * @return array<string, array{array<string, string>, string}>
     */
    public static function invalidPayloads(): array
    {
        return [
            'missing target' => [[], 'The status field is required.'],
            'unknown target' => [['target_status' => 'archived'], 'The selected status is invalid.'],
            'status instead of target_status' => [['status' => 'handled'], 'The status field is required.'],
        ];
    }

    /**
     * @param  array<string, string>  $payload
     */
    #[DataProvider('invalidPayloads')]
    public function test_an_invalid_target_returns_to_the_view_with_the_first_error_and_changes_nothing(array $payload, string $error): void
    {
        $message = ContactMessage::factory()->create();

        $this->actingAs($this->admin)->patch(route('admin.feedback.status', [$message, 'status' => 'new', 'page' => 3]), $payload)
            ->assertRedirect(route('admin.feedback', ['status' => 'new', 'page' => 3]))
            ->assertSessionHas('error', $error)
            ->assertSessionDoesntHaveErrors();

        $this->assertSame(ContactMessageStatus::New, $message->refresh()->status);
    }

    public function test_body_fields_other_than_the_target_are_ignored(): void
    {
        $other = User::factory()->create();
        $message = ContactMessage::factory()->create(['subject' => 'Original']);

        $this->actingAs($this->admin)->patch(route('admin.feedback.status', $message), [
            'target_status' => 'handled',
            'subject' => 'Changed',
            'user_id' => $other->id,
            'handled_by' => $other->id,
        ])->assertSessionHas('success');

        $message->refresh();
        $this->assertSame(['Original', $this->admin->id], [$message->subject, $message->handled_by]);
        $this->assertNotSame($other->id, $message->user_id);
    }

    public function test_an_app_user_is_refused_before_validation_and_a_guest_is_sent_to_login(): void
    {
        $message = ContactMessage::factory()->create();

        $this->actingAs(User::factory()->create())
            ->patch(route('admin.feedback.status', $message), ['target_status' => 'archived'])
            ->assertForbidden()
            ->assertSessionDoesntHaveErrors()
            ->assertSessionMissing('error');

        $this->app['auth']->forgetGuards();
        $this->patch(route('admin.feedback.status', $message), ['target_status' => 'handled'])->assertRedirect(route('login'));

        $this->assertSame(ContactMessageStatus::New, $message->refresh()->status);
    }

    public function test_an_unknown_or_non_numeric_message_is_404(): void
    {
        $this->actingAs($this->admin)->patch('/admin/feedback/999999/status', ['target_status' => 'handled'])->assertNotFound();
        $this->actingAs($this->admin)->patch('/admin/feedback/abc/status', ['target_status' => 'handled'])->assertNotFound();
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function update(ContactMessage $message, string $target, array $query = []): TestResponse
    {
        $response = $this->actingAs($this->admin)->patch(route('admin.feedback.status', [$message, ...$query]), ['target_status' => $target]);
        $this->app['auth']->forgetGuards();

        return $response;
    }
}
