<footer class="border-t border-rule text-[13px] text-muted">
    <div class="mx-auto flex max-w-7xl flex-col items-center gap-2 px-5 py-5 md:flex-row md:justify-between md:px-14 md:py-6">
        <span class="font-heading text-xl text-red italic">{{ __('app.name') }}</span>

        <nav aria-label="{{ __('app.legal.kicker') }}" class="flex flex-wrap justify-center gap-x-6">
            @foreach ($legalLinks as $link)
                <a href="{{ $link->url }}" @if ($link->active) aria-current="page" @endif
                   class="inline-flex min-h-11 items-center md:min-h-0 {{ $link->active ? 'text-ink' : '' }}">{{ $link->label }}</a>
            @endforeach
        </nav>
    </div>
</footer>
