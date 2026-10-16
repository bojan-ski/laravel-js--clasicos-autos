@props(['price'])

<x-ui.stamp tone="red" {{ $attributes->merge(['class' => 'rotate-2 bg-card motion-public:animate-stamp-in-late']) }}>
    <span class="flex flex-col items-center gap-1 px-4 py-2">
        <x-ui.kicker lang="es">{{ __('app.listings.show.price_kicker') }}</x-ui.kicker>
        <span class="font-heading text-4xl leading-none tabular-nums">{{ $price }}</span>
    </span>
</x-ui.stamp>
