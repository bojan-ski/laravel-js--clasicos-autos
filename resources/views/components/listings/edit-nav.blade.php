@props([
    'listing',
    'current',
])

<nav aria-label="{{ __('app.listings.edit.nav_label') }}" {{ $attributes->merge(['class' => 'inline-flex divide-x divide-rule rounded-sm border border-rule']) }}>
    <a href="{{ route('listings.edit', $listing) }}" @if ($current === 'data') aria-current="page" @endif
       @class([
           'inline-flex min-h-11 items-center px-3 py-1.75 text-[13px] focus-visible:-outline-offset-2 md:min-h-0',
           'text-red ring-1 ring-red ring-inset' => $current === 'data',
           'hover:bg-ink/7' => $current !== 'data',
       ])>{{ __('app.listings.edit.nav_data') }}</a>
</nav>
