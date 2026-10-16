@props(['title' => null])

<x-layouts.app :title="$title" :audience="\App\Enums\NavAudience::Admin">
    {{ $slot }}
</x-layouts.app>
