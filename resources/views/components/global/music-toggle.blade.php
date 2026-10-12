@props(['onDark' => false])

@php
    $indicator = $onDark
        ? 'data-[state=on]:border-red data-[state=on]:text-red data-[state=off]:border-cream-on-dark/40 data-[state=off]:text-cream-on-dark'
        : 'data-[state=on]:border-red data-[state=on]:text-red data-[state=off]:border-rule-strong data-[state=off]:text-muted';
@endphp

<button type="button" data-music-toggle
        aria-label="{{ __('app.music.label', ['name' => __('app.name')]) }}"
        data-label-on="{{ __('app.music.on') }}"
        data-label-off="{{ __('app.music.off') }}"
        class="min-h-11 cursor-pointer font-heading text-2xl leading-none text-red italic md:min-h-0 md:text-[30px]">{{ __('app.name') }}</button>
<span data-music-indicator hidden
      class="group grid size-6.5 place-items-center rounded-full border {{ $indicator }}">
    <x-icons.volume-2 class="hidden size-3.5 group-data-[state=on]:block" />
    <x-icons.volume-x class="hidden size-3.5 group-data-[state=off]:block" />
</span>
<audio data-music-audio loop preload="none">
    <source src="{{ asset('assets/audio/cuban-music.mp3') }}" type="audio/mpeg">
</audio>
