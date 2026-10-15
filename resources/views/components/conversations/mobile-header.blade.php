<div {{ $attributes->merge(['class' => 'sticky top-0 z-10 flex items-center gap-2 border-b border-rule bg-paper px-2 py-2 md:hidden']) }}>
    <a href="{{ $backUrl }}" class="inline-flex size-11 shrink-0 items-center justify-center text-ink-2">
        <x-icons.arrow-left class="size-5" />
        <span class="sr-only">{{ __('app.back_link') }}</span>
    </a>
    @can('view', $listing)
        @if ($imageUrl() !== null)
            <img src="{{ $imageUrl() }}" alt="" width="320" height="240" class="photo-grade size-11 shrink-0 object-cover">
        @endif
    @endcan
    <div class="flex min-w-0 flex-col">
        <p class="truncate font-heading text-lg leading-tight">{{ $name }}</p>
        @can('view', $listing)
            <a href="{{ route('listings.show', $listing) }}" class="-my-3.5 flex min-h-11 items-center text-xs text-muted underline"><span class="min-w-0 truncate">{{ $listing->name }} · <span class="tabular-nums">{{ $price() }}</span></span></a>
        @else
            <p class="truncate text-xs text-muted">{{ $listing->name }} · {{ __('app.conversations.listing.unavailable') }}</p>
        @endcan
    </div>
</div>
