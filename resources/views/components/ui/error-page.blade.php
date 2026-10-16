@props([
    'code',
    'stamp',
    'title',
    'message',
])

<section class="mx-auto grid max-w-7xl items-center gap-10 px-5 py-10 md:grid-cols-[6fr_5fr] md:gap-16 md:px-14 md:pt-20 md:pb-24">
    <x-ui.postcard tilt="right" class="relative md:p-4 md:pb-5.5">
        <img src="{{ asset('assets/images/hero.jpg') }}" alt="" width="1184" height="672"
             class="h-55 w-full object-cover photo-grade-strong md:h-95">
        <span lang="es" class="absolute top-5 -right-2 rotate-12 border-3 border-double border-red bg-card/85 px-4.5 py-2 text-sm uppercase tracking-[0.22em] text-red md:top-10 md:-right-4.5 motion-public:animate-stamp-in-late">{{ $stamp }}</span>
    </x-ui.postcard>

    <div class="flex flex-col gap-4.5">
        <span class="font-heading text-[88px] leading-[0.85] text-gold-ink tabular-nums md:text-[120px]">{{ $code }}</span>
        <h1 class="text-[38px] leading-[1.05] md:text-5xl">{{ $title }}</h1>
        <p class="text-base text-ink-2">{{ $message }}</p>
        <div class="mt-1.5 flex flex-wrap gap-2.5">
            {{ $slot }}
        </div>
    </div>
</section>
