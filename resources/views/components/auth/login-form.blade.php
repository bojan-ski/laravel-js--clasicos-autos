<div class="relative flex flex-col gap-5.5 bg-card p-6 shadow-md md:p-10 md:pb-8">
    <x-ui.stamp tone="red" aria-hidden="true" class="absolute top-4 right-4 h-14.5 w-12 md:top-6 md:right-6 md:h-19.5 md:w-16">
        <span class="grid size-full place-items-center font-heading text-lg md:text-[22px]">{{ __('app.auth.login.stamp') }}</span>
    </x-ui.stamp>

    <div class="flex flex-col gap-1.5">
        <x-ui.kicker lang="es">{{ __('app.auth.login.kicker') }}</x-ui.kicker>
        <h1 class="text-[38px] leading-none md:text-[52px]">{{ __('app.auth.login.title') }}</h1>
    </div>

    <form method="POST" action="{{ route('login.store') }}" novalidate class="flex flex-col gap-4.5">
        @csrf
        <x-forms.field name="email" type="email" autocomplete="email" required
                       :label="__('app.auth.login.email')" :placeholder="__('app.auth.login.email_placeholder')" />
        <x-forms.field name="password" type="password" autocomplete="current-password" required
                       :label="__('app.auth.login.password')" :placeholder="__('app.auth.login.password_placeholder')" />
        <x-forms.checkbox name="remember">{{ __('app.auth.login.remember') }}</x-forms.checkbox>
        <x-ui.button type="submit" size="lg" class="w-full">{{ __('app.auth.login.submit') }}</x-ui.button>
    </form>

    <div class="flex flex-col gap-2 border-t border-rule pt-4">
        <x-auth.switch-link :prompt="__('app.auth.login.switch_prompt')"
                            :label="__('app.auth.login.switch_link')"
                            :href="route('register')" />
        <x-auth.switch-link :prompt="__('app.auth.login.reset_prompt')"
                            :label="__('app.auth.login.reset_link')"
                            :href="route('password.request')" />
    </div>
</div>
