<x-layouts.app :title="__('app.listings.index.title')" :seo="$seo">
    <section class="mx-auto flex max-w-7xl flex-col gap-6 px-5 py-6 md:gap-8 md:px-14 md:py-12">
        <div class="flex flex-col gap-5 md:flex-row md:items-end md:justify-between">
            <x-ui.page-header :kicker="__('app.listings.index.kicker')" kicker-lang="es" :title="__('app.listings.index.title')" />
            <x-listings.search-form :filters="$filters" errors-id="listings-index-errors" />
        </div>

        @if ($errors->any())
            <div id="listings-index-errors" role="alert" class="flex items-start gap-2.5 border border-red-deep bg-red-deep/5 px-4 py-3 text-sm text-red-deep">
                <x-icons.circle-alert class="mt-0.5 size-4.5 shrink-0" />
                <ul class="flex flex-col gap-1">
                    @foreach ($errors->all() as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <x-listings.brand-chips :makers="$makers" :filters="$filters" />

        @if ($filtered)
            <p class="text-sm text-ink-2">{{ trans_choice('app.listings.index.count', $listings->total(), ['count' => $listings->total()]) }}</p>
        @endif

        @if ($listings->isEmpty())
            <x-listings.empty-state :filtered="$filtered" />
        @else
            <div class="grid gap-9 md:grid-cols-3">
                @foreach ($listings as $listing)
                    <x-listings.card :listing="$listing" />
                @endforeach
            </div>
        @endif

        {{ $listings->links() }}
    </section>
</x-layouts.app>
