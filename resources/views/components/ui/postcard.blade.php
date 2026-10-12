@props(['tilt' => 'none'])

@php
    $tilts = [
        'none' => '',
        'left' => '-rotate-[0.6deg]',
        'right' => '-rotate-2',
    ];
@endphp

<div {{ $attributes->merge(['class' => 'bg-card p-3 shadow-md '.$tilts[$tilt]]) }}>
    {{ $slot }}
</div>
