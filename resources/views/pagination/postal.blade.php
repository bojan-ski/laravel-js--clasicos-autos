@if ($paginator->hasPages())
    @php
        $box = 'inline-flex size-9 items-center justify-center rounded-xs border font-heading text-base tabular-nums';
        $mobileControl = 'inline-flex min-h-11 items-center gap-1.5 rounded-xs border px-4 font-heading text-base';
    @endphp

    <nav aria-label="{{ __('app.pagination.label') }}" class="flex justify-center">
        <div class="flex w-full items-center justify-between gap-3 md:hidden">
            @if ($paginator->onFirstPage())
                <span aria-hidden="true" class="{{ $mobileControl }} border-rule text-muted">‹ {{ __('app.pagination.previous') }}</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="{{ $mobileControl }} border-rule text-ink hover:bg-ink/7"><span aria-hidden="true">‹</span> {{ __('app.pagination.previous') }}</a>
            @endif

            <span class="text-sm text-ink-2">{{ __('app.pagination.page_of', ['current' => $paginator->currentPage(), 'last' => $paginator->lastPage()]) }}</span>

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="{{ $mobileControl }} border-red text-red hover:bg-red/12">{{ __('app.pagination.next') }} <span aria-hidden="true">›</span></a>
            @else
                <span aria-hidden="true" class="{{ $mobileControl }} border-rule text-muted">{{ __('app.pagination.next') }} ›</span>
            @endif
        </div>

        <ul class="hidden items-center gap-2 md:flex">
            <li>
                @if ($paginator->onFirstPage())
                    <span aria-hidden="true" class="{{ $box }} border-rule text-muted">‹</span>
                @else
                    <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="{{ $box }} border-rule text-ink hover:bg-ink/7"><span aria-hidden="true">‹</span><span class="sr-only">{{ __('app.pagination.previous') }}</span></a>
                @endif
            </li>

            @foreach ($elements as $element)
                @if (is_string($element))
                    <li><span aria-hidden="true" class="{{ $box }} border-transparent text-muted">…</span></li>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        <li>
                            @if ($page == $paginator->currentPage())
                                <span aria-current="page" class="{{ $box }} border-red text-red"><span class="sr-only">{{ __('app.pagination.page') }} </span>{{ $page }}</span>
                            @else
                                <a href="{{ $url }}" class="{{ $box }} border-rule text-ink hover:bg-ink/7"><span class="sr-only">{{ __('app.pagination.page') }} </span>{{ $page }}</a>
                            @endif
                        </li>
                    @endforeach
                @endif
            @endforeach

            <li>
                @if ($paginator->hasMorePages())
                    <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="{{ $box }} border-rule text-ink hover:bg-ink/7"><span aria-hidden="true">›</span><span class="sr-only">{{ __('app.pagination.next') }}</span></a>
                @else
                    <span aria-hidden="true" class="{{ $box }} border-rule text-muted">›</span>
                @endif
            </li>
        </ul>
    </nav>
@endif
