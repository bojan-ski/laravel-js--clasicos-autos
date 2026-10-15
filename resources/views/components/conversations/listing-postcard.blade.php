<x-ui.postcard tilt="left" {{ $attributes }}>
    @can('view', $listing)
        @if ($imageUrl() !== null)
            <img src="{{ $imageUrl() }}" alt="" width="320" height="240" class="photo-grade aspect-4/3 w-full object-cover">
        @endif
    @endcan
    <div class="flex flex-col gap-1.5 px-1 pt-3 pb-1">
        <x-ui.kicker>{{ __('app.conversations.listing.kicker') }}</x-ui.kicker>
        <p class="font-heading text-[24px] leading-tight text-red italic wrap-break-word">{{ $listing->name }}</p>
        @can('view', $listing)
            <p class="tabular-nums">{{ $price() }}</p>
            <a href="{{ route('listings.show', $listing) }}" class="inline-flex min-h-11 items-center text-red underline md:min-h-0">{{ __('app.conversations.listing.view') }}</a>
        @else
            <p class="text-[13px] text-muted">{{ __('app.conversations.listing.unavailable') }}</p>
        @endcan
    </div>
</x-ui.postcard>
