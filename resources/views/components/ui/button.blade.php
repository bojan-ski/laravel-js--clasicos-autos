@props([
    'variant' => 'primary',
    'href' => null,
    'size' => 'md',
    'type' => 'button',
])

@php
    $variants = [
        'primary' => 'border-red text-red hover:bg-red/12 active:bg-red/22',
        'secondary' => 'border-rule text-ink hover:bg-ink/7 active:bg-ink/14',
        'secondary-dark' => 'border-cream-on-dark/50 text-cream-on-dark hover:bg-cream-on-dark/10 active:bg-cream-on-dark/18',
        'ghost' => 'border-transparent text-muted hover:bg-red/10 active:bg-red/18',
        'ghost-dark' => 'border-gold text-cream-on-dark hover:bg-gold/15 active:bg-gold/25',
        'destructive' => 'border-red-deep text-red-deep hover:bg-red-deep/12 active:bg-red-deep/22',
    ];

    $sizes = [
        'md' => 'min-h-9 px-3.5 py-2 text-sm',
        'lg' => 'min-h-12 px-6 py-3 text-base',
        'hero' => 'min-h-12 px-6.5 py-3 text-[17px]',
        'responsive' =>'min-h-12 px-6 py-3 text-base md:min-h-9 md:px-3.5 md:py-2 md:text-sm',
    ];

    $classes = 'inline-flex cursor-pointer items-center justify-center gap-1.5 rounded-xs border font-heading leading-tight transition-colors duration-150 disabled:cursor-not-allowed disabled:opacity-45 '
        .$variants[$variant].' '.$sizes[$size];
@endphp

@if ($href !== null)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</button>
@endif
