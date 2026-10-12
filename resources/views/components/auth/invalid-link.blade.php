<x-auth.recovery-card :title="__('app.auth.reset.title')">
    <p role="alert" class="font-heading text-[26px] leading-tight text-red-deep italic">{{ __('app.auth.reset.invalid') }}</p>

    <x-ui.button :href="route('password.request')" size="lg" class="w-full">{{ __('app.auth.reset.request_new') }}</x-ui.button>
</x-auth.recovery-card>
