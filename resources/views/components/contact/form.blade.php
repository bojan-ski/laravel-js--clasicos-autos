@props(['action', 'replyEmail'])

<div {{ $attributes->merge(['class' => 'flex w-full max-w-160 flex-col gap-4.5 rounded-xs bg-card p-6 shadow-md md:p-10']) }}>
    <p class="text-sm text-ink-2">{{ __('app.contact.reply', ['email' => $replyEmail]) }}</p>

    <x-forms.error-summary />

    <form method="POST" action="{{ $action }}" novalidate class="flex flex-col gap-4.5">
        @csrf
        <x-forms.required-note />
        <x-forms.field name="subject" required autocomplete="off" maxlength="120"
                       :label="__('app.contact.fields.subject')" :hint="__('app.contact.hints.subject')" />
        <x-forms.textarea name="message" required maxlength="1000"
                          :label="__('app.contact.fields.message')" :hint="__('app.contact.hints.message')" />
        <x-ui.button type="submit" variant="primary" size="responsive" class="w-full md:w-auto md:self-start">{{ __('app.contact.submit') }}</x-ui.button>
    </form>
</div>
