@php
    $bars = [
        'guest' => 'border-b border-rule bg-paper text-ink',
        'member' => 'border-b border-rule bg-paper text-ink',
        'admin' => 'border-b-3 border-red bg-charcoal text-cream-on-dark',
    ];
    $onDark = $audience === \App\Enums\NavAudience::Admin;
@endphp

<header data-header-variant="{{ $audience->value }}"@if ($liveChannel !== null) data-live-channel="{{ $liveChannel }}"@endif
        class="relative z-30 has-[details[open]]:fixed has-[details[open]]:inset-x-0 has-[details[open]]:top-0 {{ $bars[$audience->value] }}">
    <div class="mx-auto flex max-w-7xl items-center gap-8 px-5 py-4 md:px-14 md:py-5">
        <div class="mr-auto flex items-center gap-2.5">
            <x-global.music-toggle :on-dark="$onDark" />
            @if ($onDark)
                <span class="border border-gold px-2 py-0.5 text-[10px] uppercase tracking-[0.2em] text-gold-light">{{ __('app.header.admin_tag') }}</span>
            @endif
        </div>

        @if ($links !== [])
            <nav aria-label="{{ __('app.a11y.main_navigation') }}" class="hidden items-center gap-7 text-sm lg:flex">
                @foreach ($links as $link)
                    <x-global.nav-link :link="$link" :audience="$audience" :live="$liveChannel !== null" />
                @endforeach
            </nav>
        @endif

        @if ($actions !== [])
            <div class="hidden items-center gap-2.5 lg:flex">
                @foreach ($actions as $action)
                    <x-global.header-action :action="$action" :audience="$audience" />
                @endforeach
            </div>
        @endif

        <x-global.mobile-menu :links="$links" :actions="$actions" :legal-links="$legalLinks" :audience="$audience" :signal="$menuSignal" :live="$liveChannel !== null" />
    </div>
</header>
