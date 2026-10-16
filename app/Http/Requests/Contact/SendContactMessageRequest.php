<?php

namespace App\Http\Requests\Contact;

use App\Models\ContactMessage;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\Attributes\RedirectToRoute;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

#[RedirectToRoute('contact')]
class SendContactMessageRequest extends FormRequest
{
    public const int MAX_ATTEMPTS = 5;

    public const int DECAY_SECONDS = 3600;

    public function authorize(): Response
    {
        return Gate::inspect('create', ContactMessage::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'subject' => ['required', 'string', 'max:120'],
            'message' => ['required', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'subject' => __('app.contact.attributes.subject'),
            'message' => __('app.contact.attributes.message'),
        ];
    }

    public function throttleKey(): string
    {
        return 'contact|'.$this->user()?->getAuthIdentifier();
    }

    protected function prepareForValidation(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), self::MAX_ATTEMPTS)) {
            return;
        }

        $minutes = (int) ceil(RateLimiter::availableIn($this->throttleKey()) / 60);

        throw ValidationException::withMessages([
            'subject' => trans_choice('app.contact.throttle', $minutes, ['minutes' => $minutes]),
        ])->redirectTo(route('contact'));
    }

    protected function passedValidation(): void
    {
        RateLimiter::hit($this->throttleKey(), self::DECAY_SECONDS);
    }
}
