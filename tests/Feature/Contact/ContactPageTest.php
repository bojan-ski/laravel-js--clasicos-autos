<?php

namespace Tests\Feature\Contact;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ContactPageTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_a_guest_is_sent_to_login(): void
    {
        $this->get(route('contact'))->assertRedirect(route('login'));
        $this->post(route('contact.store'), ['subject' => 'Hello', 'message' => 'A question.'])->assertRedirect(route('login'));

        $this->assertDatabaseCount('contact_messages', 0);
    }

    public function test_an_admin_gets_the_403_page(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('contact'))
            ->assertForbidden()
            ->assertSee(__('app.errors.403.title'));
    }

    public function test_a_suspended_user_with_a_session_is_signed_out(): void
    {
        $this->actingAs(User::factory()->suspended()->create())
            ->get(route('contact'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_an_active_app_user_sees_the_form_with_the_reply_line_naming_their_email(): void
    {
        $user = User::factory()->create(['email' => 'ada@example.com']);

        $html = $this->actingAs($user)->get(route('contact'))
            ->assertOk()
            ->assertViewIs('pages.contact.show')
            ->assertViewHas('replyEmail', 'ada@example.com')
            ->getContent();

        $this->assertMatchesRegularExpression('/lang="es"[^>]*>\s*Escríbenos/', $html);
        $this->assertStringContainsString('<h1 class="text-[38px] leading-none md:text-[56px]">Contact</h1>', $html);
        $this->assertStringContainsString('<p class="text-sm text-ink-2">The admin replies by email to ada@example.com, outside the app.</p>', $html);
        $this->assertStringContainsString('action="'.route('contact.store').'"', $html);
        $this->assertStringNotContainsString('data-error-summary', $html);
    }

    public function test_both_fields_are_required_capped_and_carry_their_persistent_hints(): void
    {
        $html = $this->actingAs(User::factory()->create())->get(route('contact'))->getContent();

        $this->assertMatchesRegularExpression('/<input id="subject" name="subject" type="text"\s+autocomplete="off"\s+required aria-required="true"\s+aria-describedby="subject-hint"[^>]*maxlength="120"/', $html);
        $this->assertMatchesRegularExpression('/<textarea id="message" name="message"\s+required aria-required="true"\s+aria-describedby="message-hint"[^>]*maxlength="1000"/', $html);
        $this->assertStringContainsString('<p id="subject-hint" class="text-xs text-muted">Max 120 characters.</p>', $html);
        $this->assertStringContainsString('<p id="message-hint" class="text-xs text-muted">Max 1000 characters.</p>', $html);
        $this->assertStringContainsString('Send message', $html);
    }

    public function test_the_contact_item_closes_the_member_nav_and_is_current_on_the_page(): void
    {
        $html = $this->actingAs(User::factory()->create())->get(route('contact'))->getContent();
        $desktopNav = Str::betweenFirst($html, 'aria-label="'.__('app.a11y.main_navigation').'"', '</nav>');

        $this->assertMatchesRegularExpression('/href="'.preg_quote(route('conversations.index'), '/').'".*href="'.preg_quote(route('contact'), '/').'"\s+aria-current="page"[^>]*>\s*Contact\s*<\/a>\s*$/s', $desktopNav);
    }

    public function test_guests_and_admins_never_see_the_contact_item(): void
    {
        $this->get(route('home'))->assertOk()->assertDontSee('href="'.route('contact').'"', false);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.users'))
            ->assertOk()
            ->assertDontSee('href="'.route('contact').'"', false);
    }
}
