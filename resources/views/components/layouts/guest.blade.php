@props([
    'title' => null,
    'width' => 'narrow',
])

@php
    $containers = [
        'narrow' => 'mx-auto w-full max-w-110 px-5 py-10 md:py-16',
        'wide' => 'mx-auto w-full max-w-7xl px-5 py-6 md:px-14 md:py-16',
    ];
@endphp

<x-layouts.app :title="$title">
    <div class="{{ $containers[$width] }}">
        {{ $slot }}
    </div>
</x-layouts.app>
