@props([
    'token',
    'email',
])

<x-auth.recovery-card :title="__('app.auth.reset.title')">
    <form method="POST" action="{{ route('password.update') }}" novalidate class="flex flex-col">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <input type="hidden" name="email" value="{{ $email }}" autocomplete="username">

        <x-auth.step number="I" :title="__('app.auth.forgot.step_one')" state="done">
            <dl>
                <div class="flex justify-between gap-4 border-b border-rule-strong py-2 text-sm">
                    <dt class="shrink-0 text-muted">{{ __('app.auth.reset.email') }}</dt>
                    <dd class="text-right break-all text-ink">{{ $email }}</dd>
                </div>
            </dl>
        </x-auth.step>

        <hr aria-hidden="true" class="my-7 border-t-2 border-dashed border-gold">

        <x-auth.step number="II" :title="__('app.auth.forgot.step_two')">
            <x-forms.field name="password" type="password" autocomplete="new-password" required
                           :label="__('app.account.password.password')" :placeholder="__('app.account.password.password_placeholder')" />
            <x-forms.field name="password_confirmation" type="password" autocomplete="new-password" required
                           :label="__('app.account.password.password_confirmation')" :placeholder="__('app.account.password.password_confirmation_placeholder')" />
            <x-ui.button type="submit" size="lg" class="w-full">{{ __('app.auth.reset.submit') }}</x-ui.button>
        </x-auth.step>
    </form>

    <x-auth.switch-link class="border-t border-rule pt-4"
                        :prompt="__('app.auth.forgot.switch_prompt')"
                        :label="__('app.auth.forgot.switch_link')"
                        :href="route('login')" />
</x-auth.recovery-card>
