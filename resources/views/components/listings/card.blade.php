<article {{ $attributes->merge(['class' => 'relative flex flex-col gap-3 bg-card p-3 pb-4 shadow-sm']) }}>
    @if ($imageUrl() !== null)
        <img src="{{ $imageUrl() }}" alt="{{ $listing->name }}" width="800" height="600" loading="lazy"
             class="h-50 w-full object-cover photo-grade">
    @else
        <div class="flex h-50 w-full items-center justify-center border border-rule bg-paper text-muted">
            <x-icons.image class="size-10" />
        </div>
    @endif

    <x-ui.stamp tone="red" class="absolute top-5.5 right-5.5 bg-card">
        <span class="block px-2.5 py-1.5 font-heading text-xl leading-none tabular-nums"><span class="sr-only">{{ __('app.listings.card.price') }}</span> {{ $price() }}</span>
    </x-ui.stamp>

    <div class="grid grid-cols-2 gap-3.5">
        <div class="flex min-w-0 flex-col gap-1.5">
            <h{{ $headingLevel }} class="line-clamp-2 font-heading text-[22px] leading-tight text-red italic wrap-break-word">{{ $listing->name }}</h{{ $headingLevel }}>
            <p class="text-xs text-muted">{{ $posted() }}</p>
            @if ($showsStatus())
                <x-listings.status-stamp :status="$listing->status" class="self-start" />
                @if ($listing->isRejected() && $listing->rejection_reason !== null)
                    <p class="text-xs text-red-deep wrap-break-word">{{ $listing->rejection_reason }}</p>
                @endif
            @endif
        </div>

        <ul class="flex min-w-0 flex-col gap-1.5 border-l border-rule pl-3.5 text-xs text-ink-2">
            <li class="border-b border-rule-strong pb-1 wrap-break-word">{{ $listing->maker->name }} · {{ $listing->model }}</li>
            <li class="border-b border-rule-strong pb-1 tabular-nums">{{ $listing->year }}</li>
            <li class="border-b border-rule-strong pb-1">{{ $listing->fuel_type->label() }}</li>
        </ul>
    </div>

    <div class="mt-auto flex items-center gap-2">
        <x-ui.button variant="primary" size="responsive" class="mr-auto" :href="$detailsUrl()">{{ __('app.listings.card.details') }}<span class="sr-only"> {{ $listing->name }}</span></x-ui.button>
        @can('compare', $listing)
            <x-listings.compare-button :listing="$listing" />
        @endcan
    </div>
</article>
