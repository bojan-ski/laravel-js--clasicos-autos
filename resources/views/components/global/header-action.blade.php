@props([
    'action',
    'audience',
    'block' => false,
])

@php
    $onDark = $audience === \App\Enums\NavAudience::Admin;
    $variant = match ($action->style) {
        \App\Enums\HeaderActionStyle::Primary => 'primary',
        \App\Enums\HeaderActionStyle::Secondary => $onDark ? 'secondary-dark' : 'secondary',
        \App\Enums\HeaderActionStyle::Ghost => $onDark ? 'ghost-dark' : 'ghost',
        \App\Enums\HeaderActionStyle::Avatar => null,
    };
    $size = $block ? 'lg' : 'md';
    $width = $block ? 'w-full' : '';
@endphp

@if ($variant === null)
    <a href="{{ $action->url }}"
       class="grid size-11 place-items-center rounded-full border border-gold font-heading text-base text-gold-ink lg:size-9">
        {{ $action->label }}
        @if ($action->ariaLabel !== null)
            <span class="sr-only">{{ $action->ariaLabel }}</span>
        @endif
    </a>
@elseif ($action->method === 'POST')
    <form method="POST" action="{{ $action->url }}" class="{{ $width }}">
        @csrf
        <x-ui.button type="submit" :variant="$variant" :size="$size" class="{{ $width }}">
            {{ $action->label }}
            @if ($action->ariaLabel !== null)
                <span class="sr-only">{{ $action->ariaLabel }}</span>
            @endif
        </x-ui.button>
    </form>
@else
    <x-ui.button :href="$action->url" :variant="$variant" :size="$size" class="{{ $width }}">
        {{ $action->label }}
        @if ($action->ariaLabel !== null)
            <span class="sr-only">{{ $action->ariaLabel }}</span>
        @endif
    </x-ui.button>
@endif
