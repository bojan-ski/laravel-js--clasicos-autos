<?php

namespace Tests\Feature\Account;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ProfilePageTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_profile_shows_the_uppercased_heading_and_the_account_rows(): void
    {
        $user = User::factory()->create([
            'username' => 'ángel ruiz',
            'email' => 'angel@example.com',
            'phone_number' => '+5355512345',
            'created_at' => '2024-03-07 10:15:00',
        ]);

        $this->actingAs($user)
            ->get(route('profile'))
            ->assertOk()
            ->assertViewHas('heading', __('app.profile.heading', ['username' => 'ÁNGEL RUIZ']))
            ->assertViewHas('accountCreated', '07.03.2024')
            ->assertSee('<title>'.e(__('app.profile.heading', ['username' => 'ÁNGEL RUIZ'])), false)
            ->assertSeeInOrder([
                __('app.profile.kicker'),
                __('app.profile.heading', ['username' => 'ÁNGEL RUIZ']),
                __('app.profile.details.title'),
                __('app.profile.details.username'), 'ángel ruiz',
                __('app.profile.details.email'), 'angel@example.com',
                __('app.profile.details.phone_number'), '+5355512345',
                __('app.profile.details.created_at'), '07.03.2024',
                __('app.account.password.title'),
            ]);
    }

    public function test_profile_marks_the_spanish_kicker(): void
    {
        $content = $this->actingAs(User::factory()->create())->get(route('profile'))->getContent();

        $this->assertMatchesRegularExpression('/<span[^>]*lang="es"[^>]*>'.preg_quote(__('app.profile.kicker'), '/').'<\/span>/', $content);
    }

    public function test_profile_password_form_puts_to_the_profile_route(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('profile'))
            ->assertSee('action="'.route('profile.password.update').'"', false)
            ->assertSee('name="_method" value="PUT"', false)
            ->assertSee('name="old_password"', false)
            ->assertSee('name="password"', false)
            ->assertSee('name="password_confirmation"', false)
            ->assertSee(__('app.account.password.submit'));
    }

    public function test_profile_renders_the_delete_account_dialog(): void
    {
        $response = $this->actingAs(User::factory()->create())
            ->get(route('profile'))
            ->assertSee('<dialog id="delete-account" aria-labelledby="delete-account-title"', false)
            ->assertSee('<h2 id="delete-account-title"', false)
            ->assertSee(__('app.profile.delete.title'))
            ->assertSee('action="'.route('profile.destroy').'"', false)
            ->assertSee('name="_method" value="DELETE"', false)
            ->assertSee(__('app.profile.delete.password_placeholder'))
            ->assertSee(__('app.profile.delete.cancel'));

        $content = $response->getContent();

        $this->assertMatchesRegularExpression('/<button type="button"[^>]*data-dialog-open="delete-account"[^>]*commandfor="delete-account"[^>]*command="show-modal"/', $content);
        $this->assertMatchesRegularExpression('/<button type="button"[^>]*data-dialog-close[^>]*commandfor="delete-account"[^>]*command="close"/', $content);
        $this->assertMatchesRegularExpression('/<input id="delete_password" name="delete_password" type="password"[^>]*autocomplete="current-password"[^>]*\srequired\s/', $content);
        $this->assertDoesNotMatchRegularExpression('/<form[^>]*action="'.preg_quote(route('profile.destroy'), '/').'"[^>]*novalidate/', $content);
    }

    public function test_profile_has_no_listings_section_and_no_safe_word(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('profile'))
            ->assertDontSee('My car listings')
            ->assertDontSee('safe word', false)
            ->assertDontSee('safe_word', false);
    }
}
