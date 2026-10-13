<x-layouts.app :title="__('app.listings.advanced.title')">
    <section class="mx-auto flex max-w-7xl flex-col gap-6 px-5 py-6 md:gap-8 md:px-14 md:py-12">
        <div class="flex flex-col gap-3">
            <x-ui.page-header :kicker="__('app.listings.advanced.kicker')" kicker-lang="es" :title="__('app.listings.advanced.title')" />
            <a href="{{ route('listings.index') }}" class="inline-flex items-center gap-1.5 self-start text-sm text-ink-2 hover:underline">
                <x-icons.arrow-left class="size-4" />
                {{ __('app.listings.advanced.back') }}
            </a>
        </div>

        @if ($errors->any())
            <div role="alert" class="flex items-start gap-2.5 border border-red-deep bg-red-deep/5 px-4 py-3 text-sm text-red-deep">
                <x-icons.circle-alert class="mt-0.5 size-4.5 shrink-0" />
                <ul class="flex flex-col gap-1">
                    @foreach ($errors->all() as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <x-listings.advanced-search-form :filters="$filters" :makers="$makers" :total="$listings->total()" />

        <div class="flex flex-wrap items-baseline gap-x-4 gap-y-1">
            <h2 class="text-[30px] leading-tight md:text-[40px]">{{ __('app.listings.advanced.results') }}</h2>
            <p class="text-sm text-muted">{{ trans_choice('app.listings.index.count', $listings->total(), ['count' => $listings->total()]) }}</p>
        </div>

        @if ($listings->isEmpty())
            <x-listings.empty-state :filtered="$filtered" />
        @else
            <div class="grid grid-cols-1 gap-7 md:grid-cols-3 lg:grid-cols-4">
                @foreach ($listings as $listing)
                    <x-listings.card :listing="$listing" :heading-level="3" />
                @endforeach
            </div>
        @endif

        {{ $listings->links() }}
    </section>
</x-layouts.app>
