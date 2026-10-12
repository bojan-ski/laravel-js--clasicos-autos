@props(['action'])

<section aria-labelledby="account-password-title" {{ $attributes->merge(['class' => 'flex flex-col gap-4 rounded-xs border border-rule bg-card p-6 shadow-sm md:p-8']) }}>
    <h2 id="account-password-title" class="text-2xl text-red italic">{{ __('app.account.password.title') }}</h2>

    <form method="POST" action="{{ $action }}" novalidate class="flex flex-col gap-4.5">
        @csrf
        @method('PUT')
        <x-forms.required-note />
        <x-forms.field name="old_password" type="password" autocomplete="current-password" required
                       :label="__('app.account.password.old_password')" :placeholder="__('app.account.password.old_password_placeholder')" />
        <x-forms.field name="password" type="password" autocomplete="new-password" required
                       :label="__('app.account.password.password')" :hint="__('app.account.password.password_placeholder')" />
        <x-forms.field name="password_confirmation" type="password" autocomplete="new-password" required
                       :label="__('app.account.password.password_confirmation')" />
        <x-ui.button type="submit" size="responsive" class="w-full md:w-auto md:self-start">{{ __('app.account.password.submit') }}</x-ui.button>
    </form>
</section>
