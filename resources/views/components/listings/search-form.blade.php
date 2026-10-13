@props(['filters'])

<form method="GET" action="{{ route('listings.index') }}" role="search" {{ $attributes->merge(['class' => 'flex w-full flex-col gap-2 md:w-auto md:flex-row md:items-center']) }}>
    <label for="search" class="sr-only">{{ __('app.listings.index.search_label') }}</label>
    <div class="flex w-full gap-2 md:w-auto">
        <input id="search" name="search" type="search" maxlength="100"
               value="{{ $filters['search'] }}"
               placeholder="{{ __('app.listings.index.search_placeholder') }}"
               class="h-12 w-full min-w-0 rounded-xs border border-rule-input bg-card px-3 text-base text-ink placeholder:text-muted md:h-10 md:w-80 md:text-sm">
        <a href="{{ route('listings.advanced') }}" class="flex size-12 shrink-0 items-center justify-center rounded-xs border border-rule text-ink-2 md:hidden">
            <x-icons.sliders-vertical class="size-5" />
            <span class="sr-only">{{ __('app.listings.index.advanced_link') }}</span>
        </a>
    </div>
    @if ($filters['maker'] !== null)
        <input type="hidden" name="maker" value="{{ $filters['maker'] }}">
    @endif
    <x-ui.button type="submit" variant="primary" size="responsive">{{ __('app.listings.index.search_button') }}</x-ui.button>
    <a href="{{ route('listings.advanced') }}" class="hidden text-sm text-ink-2 hover:underline md:inline">{{ __('app.listings.index.advanced_link') }}</a>
</form>
