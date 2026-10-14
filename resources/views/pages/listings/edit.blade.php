<x-layouts.app :title="__('app.listings.edit.title')">
    <x-ui.back-link :href="$backUrl">
        <x-listings.edit-nav :listing="$listing" current="data" />
    </x-ui.back-link>

    <section class="mx-auto flex max-w-7xl flex-col gap-6 px-5 py-6 md:gap-8 md:px-14 md:py-12">
        <div class="flex items-start justify-between gap-6">
            <x-ui.page-header class="min-w-0" :kicker="$listing->name" kicker-class="wrap-break-word" :title="__('app.listings.edit.title')" />

            @if ($listing->primaryImage !== null)
                <x-ui.postcard tilt="right" class="shrink-0">
                    <img src="{{ $listing->primaryImage->thumbUrl() }}" alt="" width="320" height="240"
                         class="photo-grade w-28 md:w-40">
                </x-ui.postcard>
            @endif
        </div>

        <x-listings.reapproval-warning :listing="$listing" />

        <x-forms.error-summary />

        <div class="flex flex-col md:grid md:grid-cols-[220px_1fr] md:gap-10">
            <div class="hidden md:block">
                <x-listings.form-contents class="sticky top-6" :sections="[
                    'section-car' => __('app.listings.form.sections.car.title'),
                    'section-engine' => __('app.listings.form.sections.engine.title'),
                    'section-history' => __('app.listings.form.sections.history.title'),
                    'section-looks' => __('app.listings.form.sections.looks.title'),
                    'section-location' => __('app.listings.form.sections.location.title'),
                    'section-description' => __('app.listings.edit.description_section'),
                ]" />
            </div>

            <form method="POST" action="{{ route('listings.update', $listing) }}" class="flex flex-col">
                @csrf
                @method('PUT')

                <div>
                    <x-listings.form-fields :options="$options" :values="$values" />

                    <x-listings.form-section id="section-description" number="VI" :title="__('app.listings.edit.description_section')">
                        <div class="md:col-span-2 lg:col-span-4">
                            <x-forms.textarea name="description" required :value="$values['description']"
                                              :label="__('app.listings.form.fields.description.label')"
                                              :placeholder="__('app.listings.form.fields.description.placeholder')"
                                              :hint="__('app.listings.form.fields.description.hint', ['max' => 5000])"
                                              maxlength="5000" />
                        </div>
                    </x-listings.form-section>
                </div>

                <div class="flex flex-col gap-5 border-t border-rule pt-6 md:flex-row md:items-center md:justify-between">
                    <p class="text-xs text-muted">{{ __('app.listings.edit.last_updated', ['date' => $lastUpdated]) }}</p>
                    <div class="hidden gap-3 md:flex">
                        <x-ui.button variant="ghost" :href="$backUrl">{{ __('app.listings.edit.cancel') }}</x-ui.button>
                        <x-ui.button variant="primary" type="submit">{{ __('app.listings.edit.submit') }}</x-ui.button>
                    </div>
                </div>

                <div data-sticky-bar class="sticky bottom-(--cookie-banner-h,0px) z-10 -mx-5 mt-5 -mb-6 border-t border-rule bg-paper p-5 md:hidden">
                    <x-ui.button variant="primary" type="submit" size="lg" class="w-full">{{ __('app.listings.edit.submit') }}</x-ui.button>
                </div>
            </form>
        </div>
    </section>
</x-layouts.app>
