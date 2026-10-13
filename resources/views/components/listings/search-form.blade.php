@props(['filters'])

<form method="GET" action="{{ route('listings.index') }}" role="search" {{ $attributes->merge(['class' => 'flex w-full flex-col gap-2 md:w-auto md:flex-row md:items-center']) }}>
    <label for="search" class="sr-only">{{ __('app.listings.index.search_label') }}</label>
    <input id="search" name="search" type="search" maxlength="100"
           value="{{ $filters['search'] }}"
           placeholder="{{ __('app.listings.index.search_placeholder') }}"
           class="h-12 w-full rounded-xs border border-rule-input bg-card px-3 text-base text-ink placeholder:text-muted md:h-10 md:w-80 md:text-sm">
    @if ($filters['maker'] !== null)
        <input type="hidden" name="maker" value="{{ $filters['maker'] }}">
    @endif
    <x-ui.button type="submit" variant="primary" size="responsive">{{ __('app.listings.index.search_button') }}</x-ui.button>
</form>
