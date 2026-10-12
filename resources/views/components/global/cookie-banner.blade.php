<section data-cookie-banner aria-label="{{ __('app.cookie_banner.label') }}" class="sticky bottom-0 z-20 border-t border-rule bg-card">
    <div class="mx-auto flex max-w-7xl flex-col gap-3 px-5 py-4 md:flex-row md:items-center md:gap-6 md:px-14">
        <p class="text-sm text-ink-2 md:flex-1">{{ __('app.cookie_banner.notice') }}</p>

        <a href="{{ route('legal.cookies') }}" class="inline-flex min-h-11 items-center self-start text-sm text-red underline md:min-h-0 md:self-auto">{{ __('app.cookie_banner.policy') }}</a>

        <form method="POST" action="{{ route('cookie-consent.store') }}" data-loading-skip>
            @csrf
            <x-ui.button type="submit" size="responsive" class="w-full md:w-auto">{{ __('app.cookie_banner.accept') }}</x-ui.button>
        </form>
    </div>
</section>
