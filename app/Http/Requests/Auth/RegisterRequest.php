<?php

namespace App\Http\Requests\Auth;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\Attributes\RedirectToRoute;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

#[RedirectToRoute('register')]
class RegisterRequest extends FormRequest
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
            'username' => ['required', 'string', 'max:64', 'unique:users,username'],
            'email' => ['required', 'string', 'email', 'max:64', 'unique:users,email'],
            'phone_number' => ['required', 'string', 'max:20', 'regex:/^[0-9\+\-\(\)\/\s]*$/', 'regex:/[0-9]/'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
            'terms_and_conditions' => ['required', 'accepted'],
            'privacy_policy' => ['required', 'accepted'],
        ];
    }

    public function throttleKey(): string
    {
        return 'register|'.$this->ip();
    }

    protected function prepareForValidation(): void
    {
        $this->ensureIsNotRateLimited();

        RateLimiter::hit($this->throttleKey(), self::DECAY_SECONDS);

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

        throw ValidationException::withMessages([
            'email' => __('auth.throttle', ['seconds' => $seconds, 'minutes' => (int) ceil($seconds / 60)]),
        ])->redirectTo(route('register'));
    }
}
