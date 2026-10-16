@props(['type', 'message' => ''])

@php
    $styles = [
        'success' => [
            'tone' => 'gold', 'tilt' => '-rotate-1', 'role' => 'status', 'live' => 'polite',
            'icon' => 'border-gold-ink text-gold-ink', 'kicker' => 'text-gold-ink', 'message' => 'text-ink',
            'kickerKey' => 'app.flash.success_kicker',
        ],
        'error' => [
            'tone' => 'red', 'tilt' => 'rotate-1', 'role' => 'alert', 'live' => 'assertive',
            'icon' => 'border-red-deep text-red-deep', 'kicker' => 'text-red-deep', 'message' => 'text-red-deep',
            'kickerKey' => 'app.flash.error_kicker',
        ],
    ];
@endphp

<x-ui.stamp :tone="$styles[$type]['tone']" class="max-w-full bg-card shadow-lg motion-public:animate-stamp-in {{ $styles[$type]['tilt'] }}"
            role="{{ $styles[$type]['role'] }}" aria-live="{{ $styles[$type]['live'] }}">
    <span class="flex items-center gap-4 px-6 py-5 md:px-8">
        <span class="grid size-11 shrink-0 place-items-center rounded-full border {{ $styles[$type]['icon'] }}">
            @if ($type === 'success')
                <x-icons.check class="size-5.5" />
            @else
                <x-icons.circle-alert class="size-5.5" />
            @endif
        </span>
        <span class="flex flex-col">
            <span lang="es" class="text-[10px] uppercase tracking-[0.2em] {{ $styles[$type]['kicker'] }}">{{ __($styles[$type]['kickerKey']) }}</span>
            <span data-flash-message class="font-heading text-[28px] leading-tight {{ $styles[$type]['message'] }}">{{ $message }}</span>
        </span>
    </span>
</x-ui.stamp>
