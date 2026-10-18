@props([
    'image',
    'name',
])

<div {{ $attributes }}>
    <x-ui.postcard class="relative">
        <img src="{{ $image->url() }}" alt="{{ __('app.listings.images.primary_alt', ['name' => $name]) }}" width="1600" height="900"
             class="photo-grade aspect-video w-full object-cover">
        <x-ui.stamp tone="red" class="absolute top-5 left-5 bg-card text-[10px] tracking-[0.2em] uppercase md:top-6 md:left-6 md:text-[11px]">
            <span class="block px-2 py-0.5">{{ __('app.listings.images.stamp') }}</span>
        </x-ui.stamp>
    </x-ui.postcard>
</div>
