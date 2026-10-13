@props(['listing', 'rows', 'viewerIsOwner'])

<section aria-labelledby="listing-owner" {{ $attributes->merge(['class' => 'flex flex-col gap-4 rounded-xs border border-rule bg-card p-6 shadow-sm']) }}>
    <div class="flex items-center gap-4">
        <x-ui.stamp tone="gold" class="h-15 w-13 shrink-0">
            <span aria-hidden="true" class="flex size-full items-center justify-center font-heading text-xl">{{ $listing->owner->initials() }}</span>
        </x-ui.stamp>
        <div class="flex min-w-0 flex-col gap-1">
            <x-ui.kicker>{{ __('app.listings.show.owner.kicker') }}</x-ui.kicker>
            <h2 id="listing-owner" class="text-2xl leading-tight wrap-break-word">{{ $listing->owner->username }}</h2>
        </div>
    </div>

    <x-ui.spec-list :rows="$rows" />

    @guest
        <p class="text-sm text-ink-2"><a href="{{ route('login') }}" class="text-red underline">{{ __('app.listings.show.owner.login') }}</a></p>
    @else
        @if ($viewerIsOwner)
            <p class="text-sm text-ink-2">{{ __('app.listings.show.owner.own') }}</p>
        @else
            @can('message', $listing)
                <x-ui.button variant="primary" size="lg" class="w-full" disabled>{{ __('app.listings.show.owner.send_message') }}</x-ui.button>
            @endcan
        @endif
    @endguest
</section>
