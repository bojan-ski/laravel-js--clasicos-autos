<x-layouts.app :title="__('app.listings.images.title')">
    <x-ui.back-link :href="$backUrl" slot-class="max-md:basis-full">
        <x-listings.edit-nav :listing="$listing" current="images" />
    </x-ui.back-link>

    <section class="mx-auto flex max-w-7xl flex-col gap-6 px-5 py-6 md:gap-8 md:px-14 md:py-12">
        <x-ui.page-header kicker-class="wrap-break-word" :title="__('app.listings.images.title')"
                          :kicker="__('app.listings.images.kicker', ['name' => $listing->name, 'count' => $imageCount, 'max' => $maxImages])" />

        <x-listings.reapproval-warning :listing="$listing" />

        <div class="grid grid-cols-1 gap-9 border-b border-rule pb-9 md:grid-cols-12">
            @if ($primaryImage !== null)
                <x-listings.primary-photo class="md:col-span-7" :image="$primaryImage" :name="$listing->name" />
            @endif

            <x-listings.add-images-card class="md:col-span-5" :action="route('listings.images.store', $listing)" :can-add="$canAddImages" />
        </div>

        <section aria-labelledby="gallery-heading" class="mt-3 md:mt-1">
            <h2 id="gallery-heading" class="sr-only">{{ __('app.listings.images.gallery_heading') }}</h2>
            <ul class="grid grid-cols-2 gap-3 md:grid-cols-4 md:gap-7">
                @foreach ($listing->images as $image)
                    <x-listings.gallery-item :listing="$listing" :image="$image" :number="$loop->iteration" :primary="$loop->first" />
                @endforeach
            </ul>
        </section>
    </section>
</x-layouts.app>
