@props([
    'links',
    'actions',
    'legalLinks',
    'audience',
    'signal' => false,
])

@php
    $onDark = $audience === \App\Enums\NavAudience::Admin;
    $panel = $onDark ? 'bg-charcoal text-cream-on-dark' : 'bg-paper text-ink';
    $rule = $onDark ? 'border-cream-on-dark/20' : 'border-rule';
@endphp

<details data-mobile-menu class="group lg:hidden">
    <summary aria-label="{{ __('app.a11y.menu') }}"
             class="relative grid size-11 cursor-pointer list-none place-items-center rounded-xs [&::-webkit-details-marker]:hidden">
        <x-icons.menu class="size-5.5 group-open:hidden" />
        <x-icons.x class="hidden size-5.5 group-open:block" />
        @if ($signal)
            <span data-menu-signal class="absolute top-2 right-2 size-2.5 rounded-full bg-red"></span>
        @endif
    </summary>

    <div class="fixed inset-0 -z-10 flex flex-col overflow-y-auto pt-19.25 {{ $panel }}">
        @if ($links !== [])
            <nav aria-label="{{ __('app.a11y.mobile_navigation') }}" class="flex flex-col px-5 py-3">
                @foreach ($links as $link)
                    <a href="{{ $link->url }}" @if ($link->active) aria-current="page" @endif
                       class="flex min-h-14 items-center justify-between border-b font-heading text-[28px] {{ $rule }} {{ $link->active ? ($onDark ? 'text-gold-light' : 'text-red') : '' }}">
                        {{ $link->label }}
                        @if ($link->count !== null && $link->countStyle === \App\Enums\NavCountStyle::Unread)
                            <x-ui.stamp tone="red" data-nav-count class="bg-card">
                                <span class="block px-2 py-px font-sans text-[10px] tracking-[0.16em]">{{ __('app.header.new_count', ['count' => $link->count]) }}</span>
                            </x-ui.stamp>
                        @elseif ($link->count !== null && $link->countStyle === \App\Enums\NavCountStyle::Selected)
                            <span data-nav-count class="font-sans text-[13px] text-gold-ink">{{ __('app.header.selected_count', ['count' => $link->count]) }}</span>
                        @elseif ($link->count !== null && $link->countStyle === \App\Enums\NavCountStyle::Queue)
                            <x-ui.stamp tone="gold" data-nav-count>
                                <span class="block px-2 py-px font-sans text-[11px] tabular-nums">{{ $link->count }}</span>
                            </x-ui.stamp>
                        @endif
                    </a>
                @endforeach
            </nav>
        @endif

        <div class="mt-auto flex flex-col gap-2.5 p-5">
            @foreach ($actions as $action)
                <x-global.header-action :action="$action" :audience="$audience" :block="true" />
            @endforeach

            <nav aria-label="{{ __('app.legal.kicker') }}" class="flex flex-col">
                @foreach ($legalLinks as $link)
                    <a href="{{ $link->url }}" @if ($link->active) aria-current="page" @endif
                       class="flex min-h-11 items-center text-sm {{ $link->active ? ($onDark ? 'text-gold-light' : 'text-red') : ($onDark ? 'text-cream-on-dark' : 'text-muted') }}">{{ $link->label }}</a>
                @endforeach
            </nav>
        </div>
    </div>
</details>
