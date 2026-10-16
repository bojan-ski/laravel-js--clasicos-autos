<footer class="border-t border-rule text-[13px] text-muted">
    <div class="mx-auto flex max-w-7xl flex-col gap-3 px-5 py-5 md:gap-4 md:px-14 md:py-6">
        <div class="flex flex-col items-center gap-2 md:flex-row md:justify-between">
            <span class="font-heading text-xl text-red italic">{{ __('app.name') }}</span>

            <nav aria-label="{{ __('app.legal.kicker') }}" class="flex flex-wrap justify-center gap-x-6">
                @foreach ($legalLinks as $link)
                    <a href="{{ $link->url }}" @if ($link->active) aria-current="page" @endif
                       class="inline-flex min-h-11 items-center md:min-h-0 {{ $link->active ? 'text-ink' : '' }}">{{ $link->label }}</a>
                @endforeach
            </nav>
        </div>

        <p data-copyright class="border-t border-rule pt-3 text-center text-xs md:pt-4 md:text-left">{{ $copyright }}</p>
    </div>
</footer>
