@props(['onDark' => false])

@php
    $indicator = $onDark
        ? 'group-data-[state=on]:border-gold-light group-data-[state=on]:text-gold-light group-data-[state=off]:border-cream-on-dark/40 group-data-[state=off]:text-cream-on-dark'
        : 'group-data-[state=on]:border-red group-data-[state=on]:text-red group-data-[state=off]:border-rule-strong group-data-[state=off]:text-muted';
@endphp

<button type="button" data-music-toggle hidden
        aria-label="{{ __('app.music.label') }}"
        data-label-on="{{ __('app.music.on') }}"
        data-label-off="{{ __('app.music.off') }}"
        class="group grid size-11 cursor-pointer place-items-center md:size-8">
    <span class="grid size-6.5 place-items-center rounded-full border transition-colors duration-150 {{ $indicator }}">
        <x-icons.volume-2 class="hidden size-3.5 group-data-[state=on]:block" />
        <x-icons.volume-x class="hidden size-3.5 group-data-[state=off]:block" />
    </span>
</button>
<audio data-music-audio loop preload="none">
    <source src="{{ asset('assets/audio/cuban-music.mp3') }}" type="audio/mpeg">
</audio>
