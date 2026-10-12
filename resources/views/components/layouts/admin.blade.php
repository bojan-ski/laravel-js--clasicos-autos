@props(['title' => null])

<x-layouts.app :title="$title" :audience="\App\Enums\NavAudience::Admin">
    <x-slot:head>{{ $head ?? '' }}</x-slot:head>

    {{ $slot }}
</x-layouts.app>
