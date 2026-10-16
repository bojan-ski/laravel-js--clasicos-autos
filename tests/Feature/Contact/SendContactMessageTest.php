<?php

namespace Tests\Feature\Contact;

use App\Enums\ContactMessageStatus;
use App\Models\ContactMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SendContactMessageTest extends TestCase
{
    use LazilyRefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->freezeSecond();
        $this->user = User::factory()->create();
    }

    protected function tearDown(): void
    {
        Cache::flush();

        parent::tearDown();
    }

    public function test_a_message_is_stored_new_and_trimmed_for_the_sender(): void
    {
        $this->send(['subject' => '  Approval time  ', 'message' => "  How long does it take?\nThanks.  "])
            ->assertRedirect(route('contact'))
            ->assertSessionHas('success', 'Your message has been sent.')
            ->assertSessionDoesntHaveErrors();

        $message = ContactMessage::query()->sole();
        $this->assertSame([$this->user->id, 'Approval time', "How long does it take?\nThanks.", ContactMessageStatus::New, null, null], [
            $message->user_id, $message->subject, $message->message, $message->status, $message->handled_at, $message->handled_by,
        ]);
    }

    public function test_after_sending_the_page_shows_the_flash_and_an_empty_form(): void
    {
        $html = $this->actingAs($this->user)->followingRedirects()
            ->post(route('contact.store'), ['subject' => 'Approval time', 'message' => 'How long does it take?'])
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Your message has been sent.', $html);
        $this->assertMatchesRegularExpression('/<input id="subject" name="subject" type="text"\s+autocomplete/', $html);
        $this->assertStringContainsString('maxlength="1000"></textarea>', $html);
    }

    /**
     * @return array<string, array{array<string, string>, array<string, string>}>
     */
    public static function invalidPayloads(): array
    {
        return [
            'missing subject' => [['subject' => '', 'message' => 'How long does it take?'], ['subject' => 'The subject field is required.']],
            'missing message' => [['subject' => 'Approval time', 'message' => '   '], ['message' => 'The message field is required.']],
            'subject over 120' => [['subject' => str_repeat('a', 121), 'message' => 'How long does it take?'], ['subject' => 'The subject field must not be greater than 120 characters.']],
            'message over 1000' => [['subject' => 'Approval time', 'message' => str_repeat('a', 1001)], ['message' => 'The message field must not be greater than 1000 characters.']],
        ];
    }

    /**
     * @param  array<string, string>  $payload
     * @param  array<string, string>  $errors
     */
    #[DataProvider('invalidPayloads')]
    public function test_an_invalid_message_is_refused_and_nothing_is_stored(array $payload, array $errors): void
    {
        $this->send($payload)->assertRedirect(route('contact'))->assertSessionHasErrors($errors);

        $this->assertDatabaseCount('contact_messages', 0);
    }

    public function test_the_limits_are_inclusive(): void
    {
        $this->send(['subject' => str_repeat('s', 120), 'message' => str_repeat('m', 1000)])->assertSessionDoesntHaveErrors();

        $this->assertDatabaseCount('contact_messages', 1);
    }

    public function test_a_refused_message_shows_the_summary_and_inline_errors_with_both_values_kept(): void
    {
        $html = $this->actingAs($this->user)->followingRedirects()
            ->post(route('contact.store'), ['subject' => str_repeat('a', 121), 'message' => 'Keep this text'])
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-error-summary', $html);
        $this->assertStringContainsString('<a href="#subject"', $html);
        $this->assertStringContainsString('<p id="subject-error" class="text-xs text-red-deep">The subject field must not be greater than 120 characters.</p>', $html);
        $this->assertStringContainsString('aria-describedby="subject-hint subject-error"', $html);
        $this->assertStringContainsString('value="'.str_repeat('a', 121).'"', $html);
        $this->assertStringContainsString('>Keep this text</textarea>', $html);
    }

    public function test_server_controlled_fields_in_the_post_are_ignored(): void
    {
        $other = User::factory()->create();
        $admin = User::factory()->admin()->create();

        $this->send([
            'subject' => 'Approval time',
            'message' => 'How long does it take?',
            'user_id' => $other->id,
            'status' => 'handled',
            'handled_by' => $admin->id,
            'handled_at' => now()->toDateTimeString(),
        ])->assertSessionDoesntHaveErrors();

        $message = ContactMessage::query()->sole();
        $this->assertSame([$this->user->id, ContactMessageStatus::New, null, null], [$message->user_id, $message->status, $message->handled_by, $message->handled_at]);
    }

    public function test_five_messages_an_hour_are_accepted_and_the_sixth_is_refused_even_when_valid(): void
    {
        foreach (range(1, 5) as $index) {
            $this->send($this->valid($index))->assertSessionDoesntHaveErrors();
        }

        $this->send($this->valid(6))
            ->assertRedirect(route('contact'))
            ->assertSessionHasErrors(['subject' => 'You have sent too many messages. Try again in 60 minutes.']);

        $this->assertDatabaseCount('contact_messages', 5);
    }

    public function test_the_throttle_refusal_shows_under_subject_and_in_the_summary_with_the_values_kept(): void
    {
        foreach (range(1, 5) as $index) {
            $this->send($this->valid($index));
        }

        $html = $this->actingAs($this->user)->followingRedirects()
            ->post(route('contact.store'), ['subject' => 'Sixth subject', 'message' => 'Sixth message'])
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('<a href="#subject" class="inline-flex min-h-11 items-center underline underline-offset-2 hover:no-underline md:min-h-0">You have sent too many messages. Try again in 60 minutes.</a>', $html);
        $this->assertStringContainsString('<p id="subject-error" class="text-xs text-red-deep">You have sent too many messages. Try again in 60 minutes.</p>', $html);
        $this->assertStringContainsString('value="Sixth subject"', $html);
        $this->assertStringContainsString('>Sixth message</textarea>', $html);
    }

    public function test_refused_submissions_do_not_count_toward_the_five(): void
    {
        foreach (range(1, 5) as $index) {
            $this->send(['subject' => '', 'message' => 'Missing subject '.$index])->assertSessionHasErrors('subject');
        }

        foreach (range(1, 5) as $index) {
            $this->send($this->valid($index))->assertSessionDoesntHaveErrors();
        }

        $this->send($this->valid(6))->assertSessionHasErrors('subject');
        $this->assertDatabaseCount('contact_messages', 5);
    }

    public function test_the_window_closes_an_hour_after_the_first_accepted_send(): void
    {
        $this->send($this->valid(1));
        $this->travel(30)->minutes();

        foreach (range(2, 5) as $index) {
            $this->send($this->valid($index))->assertSessionDoesntHaveErrors();
        }

        $this->travel(1799)->seconds();
        $this->send($this->valid(6))->assertSessionHasErrors(['subject' => 'You have sent too many messages. Try again in 1 minute.']);

        $this->travel(1)->seconds();
        $this->send($this->valid(7))->assertSessionDoesntHaveErrors();

        $this->assertDatabaseCount('contact_messages', 6);
    }

    public function test_each_user_has_their_own_limit(): void
    {
        foreach (range(1, 5) as $index) {
            $this->send($this->valid($index));
        }

        $other = User::factory()->create();
        $this->actingAs($other)->post(route('contact.store'), $this->valid(6))->assertSessionDoesntHaveErrors();

        $this->send($this->valid(7))->assertSessionHasErrors('subject');
        $this->assertSame(1, ContactMessage::query()->where('user_id', $other->id)->count());
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function send(array $payload): TestResponse
    {
        $response = $this->actingAs($this->user)->post(route('contact.store'), $payload);
        $this->app['auth']->forgetGuards();

        return $response;
    }

    /**
     * @return array<string, string>
     */
    private function valid(int $index): array
    {
        return ['subject' => "Question {$index}", 'message' => Str::repeat('Some detail. ', 3).$index];
    }
}
