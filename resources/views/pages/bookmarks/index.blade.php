<x-layouts.app :title="__('app.bookmarks.title')">
    <section class="mx-auto flex max-w-7xl flex-col gap-6 px-5 py-6 md:gap-8 md:px-14 md:py-12">
        <div class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
            <x-ui.page-header :kicker="__('app.bookmarks.kicker')" kicker-lang="es" :title="__('app.bookmarks.title')" />
            @if ($listings->total() > 0)
                <p class="text-sm text-ink-2 tabular-nums">{{ __('app.bookmarks.saved', ['count' => $listings->total()]) }}</p>
            @endif
        </div>

        @if ($listings->total() === 0)
            <x-listings.empty-state :message="__('app.bookmarks.empty')" :action-href="route('listings.index')" :action-label="__('app.bookmarks.browse')" />
        @else
            @if ($listings->isNotEmpty())
                <div class="grid gap-9 md:grid-cols-3">
                    @foreach ($listings as $listing)
                        <x-listings.card :listing="$listing" />
                    @endforeach
                </div>
            @endif

            {{ $listings->links() }}
        @endif
    </section>
</x-layouts.app>
