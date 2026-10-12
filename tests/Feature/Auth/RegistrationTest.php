<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    protected function tearDown(): void
    {
        Cache::flush();

        parent::tearDown();
    }

    public function test_guest_sees_the_register_form_with_every_label_and_the_legal_links(): void
    {
        $response = $this->get(route('register'));

        $response->assertOk()->assertViewIs('pages.auth.register');

        foreach (['username', 'email', 'phone_number', 'password', 'password_confirmation', 'terms', 'privacy', 'submit'] as $key) {
            $response->assertSee(__("app.auth.register.{$key}"));
        }

        $response->assertSee('href="'.route('legal.terms').'"', false)
            ->assertSee('href="'.route('legal.privacy').'"', false)
            ->assertSee('action="'.route('register.store').'"', false)
            ->assertDontSee('safe_word');
    }

    public function test_valid_registration_creates_an_active_app_user_without_signing_in(): void
    {
        $response = $this->post(route('register.store'), $this->validData([
            'email' => 'Ada@Example.COM',
            'phone_number' => '+1 (555) 123-45',
        ]));

        $response->assertRedirect(route('login'))
            ->assertSessionHas('success', __('app.auth.flash.registered'));
        $this->assertGuest();

        $user = User::query()->sole();
        $this->assertSame('ada lovelace', $user->username);
        $this->assertSame('ada@example.com', $user->email);
        $this->assertSame('+155512345', $user->phone_number);
        $this->assertSame(UserRole::AppUser, $user->role);
        $this->assertSame(UserStatus::Active, $user->status);
        $this->assertTrue(Hash::check('secret1', $user->password));
    }

    /**
     * @return array<string, array{array<string, string|null>, string}>
     */
    public static function invalidSubmissions(): array
    {
        return [
            'duplicate username' => [['username' => 'taken'], 'username'],
            'duplicate email' => [['email' => 'taken@example.com'], 'email'],
            'phone with letters' => [['phone_number' => '555-abc'], 'phone_number'],
            'phone without digits' => [['phone_number' => '---'], 'phone_number'],
            'phone of 21 characters' => [['phone_number' => str_repeat('1', 21)], 'phone_number'],
            'password of 5 characters' => [['password' => 'five5', 'password_confirmation' => 'five5'], 'password'],
            'unconfirmed password' => [['password_confirmation' => 'different'], 'password'],
            'terms unchecked' => [['terms_and_conditions' => null], 'terms_and_conditions'],
            'privacy unchecked' => [['privacy_policy' => null], 'privacy_policy'],
        ];
    }

    /**
     * @param  array<string, string|null>  $overrides
     */
    #[DataProvider('invalidSubmissions')]
    public function test_each_rule_rejects_its_case(array $overrides, string $field): void
    {
        User::factory()->create(['username' => 'taken', 'email' => 'taken@example.com']);

        $response = $this->from(route('register'))
            ->post(route('register.store'), array_filter($this->validData($overrides), fn (?string $value): bool => $value !== null));

        $response->assertRedirect(route('register'))->assertSessionHasErrors($field);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_role_and_status_in_the_request_are_ignored(): void
    {
        $this->post(route('register.store'), $this->validData(['role' => 'admin', 'status' => 'suspended']))
            ->assertRedirect(route('login'));

        $user = User::query()->sole();
        $this->assertSame(UserRole::AppUser, $user->role);
        $this->assertSame(UserStatus::Active, $user->status);
    }

    public function test_a_failure_redirects_to_register_even_with_a_hostile_referer(): void
    {
        $this->from('https://evil.example/phish')
            ->post(route('register.store'), $this->validData(['email' => 'not-an-email']))
            ->assertRedirect(route('register'));
    }

    public function test_the_rendered_error_state_marks_the_field_and_repopulates_everything_but_passwords(): void
    {
        $this->post(route('register.store'), $this->validData(['username' => 'ada_old', 'email' => 'not-an-email']))
            ->assertRedirect(route('register'));

        $response = $this->get(route('register'));

        $email = $this->inputTag($response->getContent(), 'email');
        $this->assertStringContainsString('aria-invalid="true"', $email);
        $this->assertStringContainsString('aria-describedby="email-error"', $email);
        $this->assertStringContainsString('border-red', $email);
        $this->assertStringNotContainsString('border-rule-strong', $email);
        $response->assertSee('id="email-error"', false);

        $this->assertStringContainsString('value="ada_old"', $this->inputTag($response->getContent(), 'username'));
        $this->assertStringNotContainsString('aria-invalid', $this->inputTag($response->getContent(), 'username'));
        $this->assertStringNotContainsString('value=', $this->inputTag($response->getContent(), 'password'));
        $this->assertStringNotContainsString('value=', $this->inputTag($response->getContent(), 'password_confirmation'));
    }

    public function test_a_password_field_never_echoes_old_input(): void
    {
        $response = $this->withSession(['_old_input' => ['username' => 'ada_old', 'password' => 'leaked1']])
            ->get(route('register'));

        $this->assertStringContainsString('value="ada_old"', $this->inputTag($response->getContent(), 'username'));
        $response->assertDontSee('leaked1');
    }

    public function test_the_sixth_submission_within_a_minute_is_refused_before_validation(): void
    {
        $this->freezeTime();

        foreach (range(1, 5) as $attempt) {
            $this->post(route('register.store'), $this->validData(['email' => 'not-an-email']));
        }

        $this->post(route('register.store'), $this->validData())
            ->assertRedirect(route('register'))
            ->assertSessionHasErrors(['email' => __('auth.throttle', ['seconds' => 60, 'minutes' => 1])]);

        $this->assertDatabaseCount('users', 0);
    }

    /**
     * @param  array<string, string|null>  $overrides
     * @return array<string, string|null>
     */
    private function validData(array $overrides = []): array
    {
        return array_merge([
            'username' => 'ada lovelace',
            'email' => 'ada@example.com',
            'phone_number' => '+44 20 7946 0958',
            'password' => 'secret1',
            'password_confirmation' => 'secret1',
            'terms_and_conditions' => '1',
            'privacy_policy' => '1',
        ], $overrides);
    }

    private function inputTag(string $html, string $name): string
    {
        $this->assertSame(1, preg_match('/<input[^>]*\bname="'.preg_quote($name, '/').'"[^>]*>/s', $html, $match));

        return $match[0];
    }
}
