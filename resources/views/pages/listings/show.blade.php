<x-layouts.app :title="$listing->name" :seo="$seo">
    <x-ui.back-link :href="$backUrl" slot-class="max-md:basis-full">
        @canany(['update', 'delete'], $listing)
            <div class="grid w-full grid-cols-2 gap-2 md:flex md:w-auto md:items-center">
                @can('update', $listing)
                    <x-ui.button variant="secondary" size="responsive" :href="route('listings.edit', $listing)">
                        {{ __('app.listings.edit.edit_control') }}
                    </x-ui.button>
                    <x-ui.button variant="secondary" size="responsive" :href="route('listings.images.edit', $listing)">
                        {{ __('app.listings.edit.edit_images_control') }}
                    </x-ui.button>
                @endcan
                @can('delete', $listing)
                    <x-listings.delete-dialog :action="route('listings.destroy', $listing)" class="col-span-2 grid md:block" />
                @endcan
            </div>
        @endcanany
    </x-ui.back-link>

    <section class="mx-auto flex max-w-7xl flex-col gap-6 px-5 py-6 md:gap-8 md:px-14 md:py-12">
        @can('update', $listing)
            <x-listings.status-panel :listing="$listing" />
        @endcan

        <div class="grid grid-cols-1 gap-6 md:grid-cols-[8fr_4fr] md:gap-8">
            <div class="order-2 flex items-start justify-between gap-6 md:order-0 md:col-span-2">
                <div class="flex min-w-0 flex-col gap-3">
                    <x-ui.kicker>{{ $specs->kicker() }}</x-ui.kicker>
                    <h1 class="text-[38px] leading-none text-red italic wrap-break-word md:text-[60px]">{{ $listing->name }}</h1>
                </div>
                <div class="flex shrink-0 items-start gap-4">
                    @can('compare', $listing)
                        <x-listings.compare-button :listing="$listing" />
                    @endcan
                    @can('bookmark', $listing)
                        <x-listings.bookmark-button :listing="$listing" />
                    @endcan
                    <div class="hidden shrink-0 md:block">
                        <x-listings.price-stamp :price="$specs->price()" />
                    </div>
                </div>
            </div>

            <x-listings.gallery :images="$listing->images" :name="$listing->name" class="order-1 self-start md:order-0" />

            <div class="order-3 flex flex-col gap-6 md:order-0">
                <section aria-label="{{ __('app.listings.show.specs.label') }}" class="rounded-xs border border-rule bg-card p-6 shadow-sm">
                    <x-ui.spec-list :rows="$specs->details()" />
                </section>

                <x-listings.owner-card :listing="$listing" :rows="$specs->owner()" :viewer-is-owner="$viewerIsOwner" />
            </div>
        </div>

        <div class="grid grid-cols-1 gap-3 md:grid-cols-[4fr_8fr] md:gap-8">
            <h2 class="text-[30px] leading-tight md:text-[40px]">{{ __('app.listings.show.specs.description') }}</h2>
            <p class="text-justify leading-7 whitespace-pre-line wrap-break-word">{{ $listing->description }}</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 md:gap-8">
            @if ($specs->exteriorInterior() !== [])
                <x-listings.spec-section :title="__('app.listings.show.specs.exterior_interior')" :rows="$specs->exteriorInterior()" />
            @endif
            <x-listings.spec-section :title="__('app.listings.show.specs.engine_and_history')" :rows="$specs->engineHistory()" />
        </div>

        <x-listings.detail-bar :listing="$listing" :price="$specs->price()" />
    </section>
</x-layouts.app>
