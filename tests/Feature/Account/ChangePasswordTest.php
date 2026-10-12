<?php

namespace Tests\Feature\Account;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ChangePasswordTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const string HOSTILE_REFERER = 'https://evil.example/phish';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function roles(): array
    {
        return [
            'app user' => ['appUser', 'profile.password.update', 'profile'],
            'admin' => ['admin', 'admin.account.password.update', 'admin.account'],
        ];
    }

    #[DataProvider('roles')]
    public function test_user_changes_the_password_and_stays_signed_in(string $role, string $route, string $page): void
    {
        $user = $this->userFor($role);
        $rememberToken = $user->remember_token;

        $this->actingAs($user)
            ->from(self::HOSTILE_REFERER)
            ->put(route($route), [
                'old_password' => 'password',
                'password' => 'new-secret',
                'password_confirmation' => 'new-secret',
            ])
            ->assertRedirect(route($page))
            ->assertSessionHas('success', __('app.account.password.updated'))
            ->assertSessionHasNoErrors();

        $fresh = $user->fresh();
        $this->assertTrue(Hash::check('new-secret', $fresh->password));
        $this->assertSame($rememberToken, $fresh->remember_token);
        $this->assertSame($user->role, $fresh->role);
        $this->assertSame($user->status, $fresh->status);
        $this->assertAuthenticatedAs($user);
    }

    /**
     * @return array<string, array{string, string, string, array<string, mixed>, string}>
     */
    public static function invalidChanges(): array
    {
        $cases = [];

        $failures = [
            'wrong old password' => [['old_password' => 'not-my-password'], 'old_password'],
            'too short' => [['password' => 'short', 'password_confirmation' => 'short'], 'password'],
            'same as old' => [['password' => 'password', 'password_confirmation' => 'password'], 'password'],
            'mismatched confirmation' => [['password_confirmation' => 'other-secret'], 'password'],
            'missing old password' => [['old_password' => ''], 'old_password'],
            'old password as an array' => [['old_password' => ['password']], 'old_password'],
        ];

        foreach (self::roles() as $roleName => [$role, $route, $page]) {
            foreach ($failures as $name => [$overrides, $field]) {
                $cases["{$roleName}: {$name}"] = [$role, $route, $page, $overrides, $field];
            }
        }

        return $cases;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    #[DataProvider('invalidChanges')]
    public function test_invalid_change_redirects_to_the_fixed_page_and_keeps_the_password(string $role, string $route, string $page, array $overrides, string $field): void
    {
        $user = $this->userFor($role);
        $hash = $user->password;

        $this->actingAs($user)
            ->from(self::HOSTILE_REFERER)
            ->put(route($route), [
                'old_password' => 'password',
                'password' => 'new-secret',
                'password_confirmation' => 'new-secret',
                ...$overrides,
            ])
            ->assertRedirect(route($page))
            ->assertInvalid([$field])
            ->assertSessionMissing('success')
            ->assertSessionMissing('_old_input.old_password')
            ->assertSessionMissing('_old_input.password');

        $this->assertSame($hash, $user->fresh()->password);
        $this->assertAuthenticatedAs($user);
    }

    public function test_wrong_old_password_renders_invalid_password_inline_without_echoing_passwords(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('profile'))
            ->put(route('profile.password.update'), [
                'old_password' => 'not-my-password',
                'password' => 'new-secret',
                'password_confirmation' => 'new-secret',
            ])
            ->assertRedirect(route('profile'));

        $content = $this->get(route('profile'))
            ->assertOk()
            ->assertSee('<p id="old_password-error" class="text-xs text-red-deep">'.__('app.account.password.invalid').'</p>', false)
            ->assertDontSee('not-my-password')
            ->assertDontSee('new-secret')
            ->getContent();

        $this->assertMatchesRegularExpression('/<input id="old_password"[^>]*aria-invalid="true" aria-describedby="old_password-error"/', $content);
        $this->assertDoesNotMatchRegularExpression('/<input id="delete_password"[^>]*aria-invalid/', $content);
    }

    private function userFor(string $role): User
    {
        return $role === 'admin' ? User::factory()->admin()->create() : User::factory()->create();
    }
}
