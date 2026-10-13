@props(['listing'])

<section aria-label="{{ __('app.listings.show.status.label') }}"
         {{ $attributes->merge(['class' => 'flex flex-wrap items-center gap-x-4 gap-y-2 border border-rule bg-card px-4 py-3']) }}>
    <x-listings.status-stamp :status="$listing->status" />
    @if ($listing->isRejected() && $listing->rejection_reason !== null)
        <p class="text-sm text-red-deep wrap-break-word">{{ __('app.listings.show.status.reason') }} {{ $listing->rejection_reason }}</p>
    @endif
    @if ($listing->owner->isSuspended())
        <p class="text-sm text-muted">{{ __('app.listings.show.status.owner_suspended') }}</p>
    @endif
</section>
