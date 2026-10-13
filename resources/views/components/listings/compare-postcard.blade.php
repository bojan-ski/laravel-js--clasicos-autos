@if ($listing !== null)
    <div {{ $attributes->merge(['class' => 'min-w-0']) }}>
        <x-ui.postcard tilt="none" class="relative flex h-full flex-col gap-2 md:gap-3 {{ $tiltClass() }}" data-compare-postcard>
            @if ($imageUrl() !== null)
                <img src="{{ $imageUrl() }}" alt="{{ $listing->name }}" width="800" height="600"
                     class="aspect-4/3 w-full object-cover photo-grade">
            @else
                <div class="flex aspect-4/3 w-full items-center justify-center border border-rule bg-paper text-muted">
                    <x-icons.image class="size-10" />
                </div>
            @endif

            <x-ui.stamp tone="red" class="absolute top-4 right-4 bg-card md:top-5.5 md:right-5.5">
                <span class="block px-1.5 py-1 font-heading text-sm leading-none tabular-nums md:px-2.5 md:py-1.5 md:text-xl"><span class="sr-only">{{ __('app.listings.card.price') }}</span> {{ $price() }}</span>
            </x-ui.stamp>

            <h2 class="line-clamp-2 font-heading text-lg leading-tight text-red italic wrap-break-word md:text-[28px]">{{ $listing->name }}</h2>

            <form method="POST" action="{{ $removeUrl() }}" class="mt-auto">
                @csrf
                @method('DELETE')
                <x-ui.button variant="ghost" type="submit">{{ __('app.compare.remove') }}<span class="sr-only"> {{ $listing->name }}</span></x-ui.button>
            </form>
        </x-ui.postcard>
    </div>
@else
    <div {{ $attributes->merge(['class' => 'flex min-h-40 items-center justify-center border-2 border-dashed border-gold']) }} data-compare-empty-slot>
        <x-icons.car class="size-12 text-gold" />
    </div>
@endif
