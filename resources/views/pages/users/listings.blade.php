<x-layouts.app :title="__('app.sellers.title', ['username' => $seller->username])">
    <x-ui.back-link :href="$backUrl" />

    <section class="mx-auto flex max-w-7xl flex-col gap-6 px-5 py-6 md:gap-8 md:px-14 md:py-12">
        <div class="flex flex-wrap items-center gap-6">
            <x-ui.stamp tone="red" aria-hidden="true" class="h-19 w-16 shrink-0 md:h-27 md:w-23">
                <span class="grid size-full place-items-center font-heading text-2xl text-red md:text-4xl">{{ $seller->initials() }}</span>
            </x-ui.stamp>
            <div class="flex min-w-0 flex-col gap-2">
                <x-ui.page-header :kicker="__('app.sellers.kicker')" kicker-lang="es" :title="__('app.sellers.title', ['username' => $seller->username])" class="min-w-0 wrap-break-word" />
                <p class="text-sm text-ink-2 tabular-nums">{{ trans_choice('app.sellers.joined', $listings->total(), ['date' => $joined, 'count' => $listings->total()]) }}</p>
            </div>
        </div>

        @if ($listings->total() === 0)
            <x-listings.empty-state :message="__('app.sellers.empty')" />
        @else
            @if ($listings->isNotEmpty())
                <div class="grid gap-9 md:grid-cols-3">
                    @foreach ($listings as $listing)
                        <x-listings.card :listing="$listing" :show-status="$showsStatus" />
                    @endforeach
                </div>
            @endif

            {{ $listings->links() }}
        @endif
    </section>
</x-layouts.app>
