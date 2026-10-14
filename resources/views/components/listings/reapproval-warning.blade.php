@props(['listing'])

@if ($listing->shouldReturnToPendingOnEdit())
    <div {{ $attributes->merge(['class' => 'flex items-start gap-3 border border-gold bg-card px-4 py-3']) }}>
        <x-icons.circle-alert class="mt-0.5 size-5 shrink-0 text-gold-ink" />
        <p class="text-ink">{{ __('app.listings.edit.reapproval_warning') }}</p>
    </div>
@endif
