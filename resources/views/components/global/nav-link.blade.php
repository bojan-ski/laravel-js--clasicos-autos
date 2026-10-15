@props(['link', 'audience', 'live' => false])

@php
    $onDark = $audience === \App\Enums\NavAudience::Admin;
    $state = match (true) {
        $link->active && $onDark => 'border-gold-light text-gold-light',
        $link->active => 'border-red text-red',
        $onDark => 'border-transparent text-cream-on-dark hover:text-gold-light',
        default => 'border-transparent text-ink hover:text-red-deep',
    };
@endphp

<a href="{{ $link->url }}" @if ($link->active) aria-current="page" @endif
   class="relative border-b pb-0.5 transition-colors duration-150 {{ $state }}">
    {{ $link->label }}
    @if ($link->countStyle === \App\Enums\NavCountStyle::Unread && ($link->count !== null || $live))
        <span data-unread-badge {{ $link->count !== null ? 'data-nav-count' : 'hidden' }} data-template=":count" class="absolute -top-2.5 -right-4 h-4.5 min-w-4.5 rounded-full bg-red px-1.25 text-center text-[11px] leading-4.5 text-cream-on-dark tabular-nums"><span data-unread-badge-text>{{ $link->count ?? 0 }}</span><span class="sr-only"> {{ __('app.header.unread') }}</span></span>
    @elseif ($link->count !== null && $link->countStyle === \App\Enums\NavCountStyle::Queue)
        <x-ui.stamp tone="gold" data-nav-count class="ml-1.5 align-middle text-[11px] leading-none tabular-nums">
            <span class="block px-1.5 py-0.5 text-gold-light">{{ $link->count }}</span>
        </x-ui.stamp>
    @endif
</a>
