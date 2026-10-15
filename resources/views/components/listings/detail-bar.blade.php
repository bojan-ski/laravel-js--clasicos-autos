@props(['listing', 'price'])

<div data-sticky-bar role="region" aria-label="{{ __('app.listings.show.bar.label') }}"
     {{ $attributes->merge(['class' => 'sticky bottom-(--cookie-banner-h,0px) z-10 -mx-5 -mb-6 flex items-center gap-4 border-t border-rule bg-paper px-5 py-3 md:hidden']) }}>
    <div class="flex shrink-0 flex-col gap-0.5">
        <x-ui.kicker lang="es">{{ __('app.listings.show.price_kicker') }}</x-ui.kicker>
        <span class="font-heading text-3xl leading-none tabular-nums">{{ $price }}</span>
    </div>

    @guest
        <a href="{{ route('login') }}" class="flex min-h-12 flex-1 items-center justify-end text-right text-sm text-red underline">{{ __('app.listings.show.owner.login') }}</a>
    @else
        @can('message', $listing)
            <x-ui.button variant="primary" size="lg" class="flex-1" :href="route('conversations.create', $listing)">{{ __('app.listings.show.owner.send_message') }}</x-ui.button>
        @endcan
    @endguest
</div>
