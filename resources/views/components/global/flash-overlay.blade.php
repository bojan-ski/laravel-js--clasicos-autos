@props(['sticky' => false])

<div data-flash @if ($sticky) data-flash-sticky @endif class="pointer-events-none fixed inset-0 z-60 flex flex-col items-center gap-6 bg-[rgba(45,43,43,.25)] px-5 pt-37.5 transition-opacity duration-200 data-[state=leaving]:opacity-0 motion-public:animate-fade-in">
    {{ $slot }}
</div>
