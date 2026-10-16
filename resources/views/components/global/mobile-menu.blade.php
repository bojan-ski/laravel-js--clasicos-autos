@props([
    'links',
    'actions',
    'legalLinks',
    'audience',
    'signal' => false,
    'live' => false,
])

@php
    $onDark = $audience === \App\Enums\NavAudience::Admin;
    $panel = $onDark ? 'bg-charcoal text-cream-on-dark' : 'bg-paper text-ink';
    $rule = $onDark ? 'border-cream-on-dark/20' : 'border-rule';
    $menuClass = $onDark ? 'group xl:hidden' : 'group lg:hidden';
    $desktopQuery = $onDark ? '(min-width: 80rem)' : '(min-width: 64rem)';
@endphp

<details data-mobile-menu data-desktop-query="{{ $desktopQuery }}" class="{{ $menuClass }}">
    <summary class="relative grid size-11 cursor-pointer list-none place-items-center rounded-xs [&::-webkit-details-marker]:hidden">
        <span class="sr-only">{{ __('app.a11y.menu') }}</span>
        <x-icons.menu class="size-5.5 group-open:hidden" />
        <x-icons.x class="hidden size-5.5 group-open:block" />
        @if ($signal || $live)
            <span data-unread-dot {{ $signal ? 'data-menu-signal' : 'hidden' }} class="absolute top-2 right-2 size-2.5 rounded-full bg-red"><span class="sr-only"> {{ __('app.a11y.menu_unread') }}</span></span>
        @endif
    </summary>

    <div class="fixed inset-0 -z-10 flex flex-col overflow-y-auto pt-19.25 {{ $panel }}">
        @if ($links !== [])
            <nav aria-label="{{ __('app.a11y.mobile_navigation') }}" class="flex flex-col px-5 py-3">
                @foreach ($links as $link)
                    <a href="{{ $link->url }}" @if ($link->active) aria-current="page" @endif
                       class="flex min-h-14 items-center justify-between border-b font-heading text-[28px] {{ $rule }} {{ $link->active ? ($onDark ? 'text-gold-light' : 'text-red') : '' }}">
                        {{ $link->label }}
                        @if ($link->countStyle === \App\Enums\NavCountStyle::Unread && ($link->count !== null || $live))
                            <x-ui.stamp tone="red" data-unread-badge :data-nav-count="$link->count !== null" :hidden="$link->count === null" :data-template="__('app.header.new_count')" class="bg-card">
                                <span data-unread-badge-text class="block px-2 py-px font-sans text-[10px] tracking-[0.16em]">{{ __('app.header.new_count', ['count' => $link->count ?? 0]) }}</span>
                            </x-ui.stamp>
                        @elseif ($link->countStyle === \App\Enums\NavCountStyle::Selected)
                            <span data-selected-count data-template="{{ __('app.header.selected_count') }}" {{ $link->count !== null ? 'data-nav-count' : 'hidden' }} class="font-sans text-[13px] text-gold-ink">{{ __('app.header.selected_count', ['count' => $link->count ?? 0]) }}</span>
                        @elseif ($link->count !== null && $link->countStyle === \App\Enums\NavCountStyle::Queue)
                            <x-ui.stamp tone="gold" data-nav-count>
                                <span class="block px-2 py-px font-sans text-[11px] tabular-nums {{ $onDark ? 'text-gold-light' : '' }}">{{ $link->count }}@if ($link->countLabel !== null)<span class="sr-only"> {{ $link->countLabel }}</span>@endif</span>
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
