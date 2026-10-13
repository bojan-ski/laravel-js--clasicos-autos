@props([
    'title' => null,
    'audience' => null,
])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="has-data-cookie-banner:scroll-pb-60 md:has-data-cookie-banner:scroll-pb-28 max-md:[&:has([data-sticky-bar]):not(:has([data-cookie-banner]))]:scroll-pb-24">
<head>
    <x-global.head :title="$title">{{ $head ?? '' }}</x-global.head>
</head>
<body class="flex min-h-screen flex-col">
    <a href="#main" class="sr-only z-80 bg-card px-4 py-2 text-sm text-red focus:not-sr-only focus:fixed focus:top-3 focus:left-3">{{ __('app.a11y.skip_to_content') }}</a>

    <x-global.header :audience="$audience" />

    <main id="main" tabindex="-1" class="flex-1 outline-none">
        {{ $slot }}
    </main>

    <x-global.footer />
    <x-global.flash />
    <x-global.loading-overlay />
    <x-global.back-to-top />
    <x-global.cookie-banner />
</body>
</html>
