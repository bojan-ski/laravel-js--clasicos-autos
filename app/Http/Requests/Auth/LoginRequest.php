<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\Attributes\RedirectToRoute;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

#[RedirectToRoute('login')]
class LoginRequest extends FormRequest
{
    public const int MAX_ATTEMPTS = 5;

    public const int DECAY_SECONDS = 60;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:64'],
            'password' => ['required', 'string'],
            'remember' => ['sometimes', 'boolean'],
        ];
    }

    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $credentials = $this->only('email', 'password');

        if (! Auth::validate($credentials)) {
            RateLimiter::hit($this->throttleKey(), self::DECAY_SECONDS);

            $this->refuse([
                'email' => __('app.auth.login.failed'),
                'password' => __('app.auth.login.failed'),
            ]);
        }

        /** @var User $user */
        $user = Auth::getLastAttempted();

        if ($user->isSuspended()) {
            $this->refuse(['email' => $this->suspensionNotice($user)]);
        }

        if (config('hashing.rehash_on_login', true)) {
            Auth::getProvider()->rehashPasswordIfRequired($user, $credentials);
        }

        Auth::login($user, $this->boolean('remember'));

        RateLimiter::clear($this->throttleKey());
    }

    public function throttleKey(): string
    {
        return Str::lower((string) $this->input('email')).'|'.$this->ip();
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => Str::lower($this->input('email'))]);
        }
    }

    private function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), self::MAX_ATTEMPTS)) {
            return;
        }

        $seconds = RateLimiter::availableIn($this->throttleKey());

        $this->refuse([
            'email' => __('auth.throttle', ['seconds' => $seconds, 'minutes' => (int) ceil($seconds / 60)]),
        ]);
    }

    private function suspensionNotice(User $user): string
    {
        if ($user->suspended_until === null) {
            return __('app.auth.login.suspended_indefinitely', ['reason' => $user->suspension_reason]);
        }

        return __('app.auth.login.suspended_until', [
            'date' => $user->suspended_until->format('d.m.Y'),
            'reason' => $user->suspension_reason,
        ]);
    }

    /**
     * @param  array<string, string>  $messages
     */
    private function refuse(array $messages): never
    {
        throw ValidationException::withMessages($messages)->redirectTo(route('login'));
    }
}
