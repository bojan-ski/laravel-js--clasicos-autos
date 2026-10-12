@props(['title' => null])

<x-layouts.app :title="$title">
    <x-slot:head>{{ $head ?? '' }}</x-slot:head>

    <div class="mx-auto w-full max-w-110 px-5 py-10 md:py-16">
        {{ $slot }}
    </div>
</x-layouts.app>
