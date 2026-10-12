@props(['tone' => 'red'])

@php
    $tones = [
        'red' => ['outer' => 'border-red text-red', 'inner' => 'border-red'],
        'gold' => ['outer' => 'border-gold text-gold-ink', 'inner' => 'border-gold'],
    ];
@endphp

<span {{ $attributes->merge(['class' => 'inline-block border-2 border-dashed p-1 '.$tones[$tone]['outer']]) }}>
    <span class="block size-full border {{ $tones[$tone]['inner'] }}">{{ $slot }}</span>
</span>
