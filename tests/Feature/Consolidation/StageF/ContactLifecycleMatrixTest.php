<?php

namespace Tests\Feature\Consolidation\StageF;

use App\Models\ContactMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ContactLifecycleMatrixTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const string NOW = '2026-10-16 10:00:00';

    private const string PAST = '2026-10-13 10:00:00';

    private User $admin;

    private User $previousAdmin;

    private User $sender;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->travelTo(self::NOW);

        $this->admin = User::factory()->admin()->create();
        $this->previousAdmin = User::factory()->admin()->create();
        $this->sender = User::factory()->create();
        RateLimiter::clear('contact|'.$this->sender->id);
    }

    public function test_an_app_user_sends_a_message_and_it_opens_as_new(): void
    {
        $this->actingAs($this->sender)
            ->post(route('contact.store'), ['subject' => 'Approval time', 'message' => 'How long does an approval take?'])
            ->assertRedirect(route('contact'))
            ->assertSessionHas('success', __('app.contact.flash.sent'));

        $this->assertSame(
            ['user_id' => $this->sender->id, 'subject' => 'Approval time', 'message' => 'How long does an approval take?', 'status' => 'new', 'handled_at' => null, 'handled_by' => null],
            (array) DB::table('contact_messages')->first(['user_id', 'subject', 'message', 'status', 'handled_at', 'handled_by']),
        );
    }

    public function test_a_sixth_accepted_send_within_the_hour_is_refused_on_subject_and_a_failed_send_never_counts(): void
    {
        $this->send(['subject' => '', 'message' => 'Missing its subject'])->assertSessionHasErrors('subject');
        foreach (range(1, 5) as $index) {
            $this->send(['subject' => "Subject {$index}", 'message' => "Message {$index}"])->assertSessionDoesntHaveErrors();
        }

        $this->send(['subject' => 'Sixth subject', 'message' => 'Sixth message'])
            ->assertRedirect(route('contact'))
            ->assertSessionHasErrors(['subject' => trans_choice('app.contact.throttle', 60, ['minutes' => 60])])
            ->assertSessionHasInput(['subject' => 'Sixth subject', 'message' => 'Sixth message']);

        $this->assertSame(5, ContactMessage::query()->count());
        $this->assertSame(0, ContactMessage::query()->where('subject', 'Sixth subject')->count());
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function cells(): array
    {
        return [
            'mark handled from new' => ['handled', 'new', 'HANDLED'],
            'mark handled from handled' => ['handled', 'handled', 'G'],
            'mark new from new' => ['new', 'new', 'G'],
            'mark new from handled' => ['new', 'handled', 'NEW'],
        ];
    }

    #[DataProvider('cells')]
    public function test_each_status_change_from_each_state(string $target, string $from, string $cell): void
    {
        $message = $this->messageIn($from);
        $before = $this->columns($message);

        $response = $this->actingAs($this->admin)->patch(route('admin.feedback.status', $message), ['target_status' => $target])
            ->assertRedirect(route('admin.feedback'));

        match ($cell) {
            'HANDLED' => $response->assertSessionHas('success', __('app.admin.feedback.flash.handled')),
            'NEW' => $response->assertSessionHas('success', __('app.admin.feedback.flash.new')),
            'G' => $response->assertSessionHas('error', __('app.admin.feedback.flash.error')),
        };

        $this->assertSame(match ($cell) {
            'HANDLED' => ['status' => 'handled', 'handled_at' => self::NOW, 'handled_by' => $this->admin->id],
            'NEW' => ['status' => 'new', 'handled_at' => null, 'handled_by' => null],
            'G' => $before,
        }, $this->columns($message));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function refusedActors(): array
    {
        return [
            'a guest opens contact' => ['guest', 'contact'],
            'a guest sends' => ['guest', 'contact.store'],
            'a guest changes a status' => ['guest', 'admin.feedback.status'],
            'an admin opens contact' => ['admin', 'contact'],
            'an admin sends' => ['admin', 'contact.store'],
            'an app user changes a status' => ['app user', 'admin.feedback.status'],
            'a suspended app user opens contact' => ['suspended', 'contact'],
            'a suspended app user sends' => ['suspended', 'contact.store'],
            'a suspended app user changes a status' => ['suspended', 'admin.feedback.status'],
        ];
    }

    #[DataProvider('refusedActors')]
    public function test_a_refused_actor_changes_nothing(string $actor, string $route): void
    {
        $message = $this->messageIn('new');
        $before = $this->columns($message);

        match ($actor) {
            'guest' => null,
            'admin' => $this->actingAs($this->admin),
            'app user' => $this->actingAs($this->sender),
            'suspended' => $this->actingAs(User::factory()->suspended(now()->addDays(3))->create()),
        };

        $response = match ($route) {
            'contact' => $this->get(route('contact')),
            'contact.store' => $this->post(route('contact.store'), ['subject' => 'Refused subject', 'message' => 'Refused message']),
            'admin.feedback.status' => $this->patch(route('admin.feedback.status', $message), ['target_status' => 'handled']),
        };

        in_array($actor, ['guest', 'suspended'], true)
            ? $response->assertRedirect(route('login'))
            : $response->assertForbidden()->assertSee(__('app.errors.403.title'));

        $this->assertSame($before, $this->columns($message));
        $this->assertSame(1, ContactMessage::query()->count());
    }

    /**
     * @param  array<string, string>  $payload
     */
    private function send(array $payload): TestResponse
    {
        return $this->actingAs($this->sender)->post(route('contact.store'), $payload);
    }

    private function messageIn(string $from): ContactMessage
    {
        $message = ContactMessage::factory()->for($this->sender)->create();

        if ($from === 'handled') {
            $message->forceFill(['status' => 'handled', 'handled_at' => self::PAST, 'handled_by' => $this->previousAdmin->id])->save();
        }

        return $message;
    }

    /**
     * @return array{status: string, handled_at: ?string, handled_by: ?int}
     */
    private function columns(ContactMessage $message): array
    {
        return (array) DB::table('contact_messages')->where('id', $message->id)->first(['status', 'handled_at', 'handled_by']);
    }
}
