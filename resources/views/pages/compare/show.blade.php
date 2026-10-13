<x-layouts.app :title="__('app.compare.title')">
    <x-ui.back-link :href="$backUrl" />

    <section class="mx-auto flex max-w-7xl flex-col gap-6 px-5 py-6 md:gap-10 md:px-14 md:py-12">
        <div class="flex flex-col items-center gap-4 md:grid md:grid-cols-[1fr_auto_1fr]">
            <h1 class="text-center text-[38px] leading-none text-red italic md:col-start-2 md:text-[52px]">{{ __('app.compare.title') }}</h1>
            @if ($listings->isNotEmpty())
                <form method="POST" action="{{ route('compare.clear') }}" class="md:justify-self-end">
                    @csrf
                    @method('DELETE')
                    <x-ui.button variant="secondary" type="submit">{{ __('app.compare.clear_all') }}</x-ui.button>
                </form>
            @endif
        </div>

        @if ($listings->isEmpty())
            <x-listings.empty-state :message="__('app.compare.empty')" />
        @else
            <div class="grid grid-cols-2 gap-4 md:grid-cols-[1fr_260px_1fr] md:gap-0">
                <x-listings.compare-postcard :listing="$listings[0]" tilt="left" />
                <p aria-hidden="true" class="hidden items-center justify-center font-heading text-[64px] leading-none text-gold italic md:flex">{{ __('app.compare.versus') }}</p>
                <x-listings.compare-postcard :listing="$listings[1] ?? null" tilt="right" />
            </div>

            <x-listings.compare-table :rows="$rows" :names="$listings->pluck('name')->all()" />
        @endif
    </section>
</x-layouts.app>
