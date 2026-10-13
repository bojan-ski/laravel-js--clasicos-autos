@props(['tone' => 'red'])

@php
    $tones = [
        'red' => ['outer' => 'border-red text-red', 'inner' => 'border-red'],
        'gold' => ['outer' => 'border-gold text-gold-ink', 'inner' => 'border-gold'],
        'status-pending' => ['outer' => 'border-status-pending text-status-pending bg-status-pending/10', 'inner' => 'border-status-pending'],
        'status-approved' => ['outer' => 'border-status-approved text-status-approved bg-status-approved/10', 'inner' => 'border-status-approved'],
        'status-rejected' => ['outer' => 'border-status-rejected text-status-rejected bg-status-rejected/10', 'inner' => 'border-status-rejected'],
    ];
@endphp

<span {{ $attributes->merge(['class' => 'inline-block border-2 border-dashed p-1 '.$tones[$tone]['outer']]) }}>
    <span class="block size-full border {{ $tones[$tone]['inner'] }}">{{ $slot }}</span>
</span>
