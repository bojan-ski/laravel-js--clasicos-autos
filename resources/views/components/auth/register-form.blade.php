<div class="relative grid bg-card p-5 shadow-md md:grid-cols-[5fr_7fr] md:p-10">
    <div class="flex flex-col gap-3 pr-16 md:gap-5 md:border-r md:border-rule-strong md:pr-10">
        <x-ui.kicker lang="es">{{ __('app.auth.register.kicker') }}</x-ui.kicker>
        <h1 class="text-[38px] leading-none md:text-[56px]">{{ __('app.auth.register.title') }}</h1>
        <p class="font-heading text-lg leading-snug text-ink-2 italic md:text-[22px]">{{ __('app.auth.register.intro') }}</p>
        <img src="{{ asset('assets/images/hero.jpg') }}" alt="" width="1184" height="672"
             class="mt-auto hidden h-65 w-full object-cover photo-grade md:block">
    </div>

    <div class="mt-6 flex flex-col gap-4.5 md:mt-0 md:pl-10">
        <x-ui.stamp tone="gold" lang="es" aria-hidden="true" class="absolute top-5 right-5 h-14.5 w-12 md:static md:h-25 md:w-21 md:self-end">
            <span class="flex size-full flex-col items-center justify-center">
                <span class="hidden text-[9px] uppercase tracking-[0.16em] md:block">{{ __('app.auth.register.stamp_country') }}</span>
                <span class="font-heading text-lg leading-none md:text-[28px]">{{ __('app.auth.register.stamp_year') }}</span>
                <span class="hidden text-[9px] uppercase tracking-[0.16em] md:block">{{ __('app.auth.register.stamp_post') }}</span>
            </span>
        </x-ui.stamp>

        <form method="POST" action="{{ route('register.store') }}" novalidate class="flex flex-col gap-4.5">
            @csrf
            <x-forms.required-note />
            <div class="grid gap-4.5 md:grid-cols-2 md:gap-x-5">
                <x-forms.field name="username" autocomplete="username" required
                               :label="__('app.auth.register.username')" :placeholder="__('app.auth.register.username_placeholder')" />
                <x-forms.field name="email" type="email" autocomplete="email" required
                               :label="__('app.auth.register.email')" :placeholder="__('app.auth.register.email_placeholder')" />
                <div class="md:col-span-2">
                    <x-forms.field name="phone_number" type="tel" autocomplete="tel" required
                                   :label="__('app.auth.register.phone_number')" :placeholder="__('app.auth.register.phone_number_placeholder')" />
                </div>
                <x-forms.field name="password" type="password" autocomplete="new-password" required
                               :label="__('app.auth.register.password')" :hint="__('app.auth.register.password_placeholder')" />
                <x-forms.field name="password_confirmation" type="password" autocomplete="new-password" required
                               :label="__('app.auth.register.password_confirmation')" />
            </div>

            <div class="flex flex-col border-t border-rule pt-3 md:flex-row md:gap-7 md:pt-4">
                <x-forms.checkbox name="terms_and_conditions" required>
                    {{ __('app.auth.register.agree') }} <a href="{{ route('legal.terms') }}" class="text-red underline underline-offset-2">{{ __('app.auth.register.terms') }}</a>
                </x-forms.checkbox>
                <x-forms.checkbox name="privacy_policy" required>
                    {{ __('app.auth.register.agree') }} <a href="{{ route('legal.privacy') }}" class="text-red underline underline-offset-2">{{ __('app.auth.register.privacy') }}</a>
                </x-forms.checkbox>
            </div>

            <div class="flex flex-col-reverse gap-4 md:flex-row md:items-center md:justify-between">
                <x-auth.switch-link :prompt="__('app.auth.register.switch_prompt')" :label="__('app.auth.register.switch_link')" :href="route('login')" />
                <x-ui.button type="submit" size="lg" class="w-full md:w-auto md:px-9">{{ __('app.auth.register.submit') }}</x-ui.button>
            </div>
        </form>
    </div>
</div>
