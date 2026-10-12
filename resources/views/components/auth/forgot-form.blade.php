<x-auth.recovery-card :title="__('app.auth.forgot.title')">
    <div class="flex flex-col">
        <form method="POST" action="{{ route('password.email') }}" novalidate>
            @csrf
            <x-auth.step number="I" :title="__('app.auth.forgot.step_one')">
                <x-forms.required-note />
                <x-forms.field name="email" type="email" autocomplete="email" required
                               :label="__('app.auth.forgot.email')" :placeholder="__('app.auth.forgot.email_placeholder')" />
                <x-ui.button type="submit" size="lg" class="w-full">{{ __('app.auth.forgot.submit') }}</x-ui.button>
            </x-auth.step>
        </form>

        <hr aria-hidden="true" class="my-7 border-t-2 border-dashed border-gold">

        <x-auth.step number="II" :title="__('app.auth.forgot.step_two')" state="inactive" />
        <p class="mt-2 text-[13px] text-muted">{{ __('app.auth.forgot.step_two_hint') }}</p>
    </div>

    <x-auth.switch-link class="border-t border-rule pt-4"
                        :prompt="__('app.auth.forgot.switch_prompt')"
                        :label="__('app.auth.forgot.switch_link')"
                        :href="route('login')" />
</x-auth.recovery-card>
