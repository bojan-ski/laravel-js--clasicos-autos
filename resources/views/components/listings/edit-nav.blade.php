@props([
    'listing',
    'current',
])

<nav aria-label="{{ __('app.listings.edit.nav_label') }}" {{ $attributes->merge(['class' => 'flex w-full divide-x divide-rule rounded-sm border border-rule md:inline-flex md:w-auto']) }}>
    <a href="{{ route('listings.edit', $listing) }}" @if ($current === 'data') aria-current="page" @endif
       @class([
           'inline-flex min-h-11 flex-1 items-center justify-center px-3 py-1.75 text-[13px] focus-visible:-outline-offset-2 md:min-h-0 md:flex-none',
           'text-red ring-1 ring-red ring-inset' => $current === 'data',
           'hover:bg-ink/7' => $current !== 'data',
       ])>{{ __('app.listings.edit.nav_data') }}</a>
    <a href="{{ route('listings.images.edit', $listing) }}" @if ($current === 'images') aria-current="page" @endif
       @class([
           'inline-flex min-h-11 flex-1 items-center justify-center px-3 py-1.75 text-[13px] focus-visible:-outline-offset-2 md:min-h-0 md:flex-none',
           'text-red ring-1 ring-red ring-inset' => $current === 'images',
           'hover:bg-ink/7' => $current !== 'images',
       ])>{{ __('app.listings.edit.nav_images') }}</a>
</nav>
