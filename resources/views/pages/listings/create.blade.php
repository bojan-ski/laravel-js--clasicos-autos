<x-layouts.app :title="__('app.listings.create.title')">
    <section class="mx-auto flex max-w-7xl flex-col gap-6 px-5 py-6 md:gap-8 md:px-14 md:py-12">
        <x-ui.page-header :kicker="__('app.listings.create.kicker')" kicker-lang="es" :title="__('app.listings.create.title')" />

        <x-forms.error-summary />

        <div class="flex flex-col md:grid md:grid-cols-[220px_1fr] md:gap-10">
            <div class="hidden md:block">
                <x-listings.form-contents class="sticky top-6" :sections="[
                    'section-car' => __('app.listings.form.sections.car.title'),
                    'section-engine' => __('app.listings.form.sections.engine.title'),
                    'section-history' => __('app.listings.form.sections.history.title'),
                    'section-looks' => __('app.listings.form.sections.looks.title'),
                    'section-location' => __('app.listings.form.sections.location.title'),
                    'section-description' => __('app.listings.form.sections.description.title'),
                ]" />
            </div>

            <div class="flex flex-col">
                <x-listings.form-progress :count="6" class="sticky top-0 z-10 -mx-5 px-5 md:hidden" />

                <form method="POST" action="{{ route('listings.store') }}" enctype="multipart/form-data" class="flex flex-col">
                    @csrf

                    <div>
                        <x-listings.form-fields :options="$options" />

                        <x-listings.form-section id="section-description" number="VI" :title="__('app.listings.form.sections.description.title')">
                            <div class="md:col-span-2 lg:col-span-4">
                                <x-forms.textarea name="description" required
                                                  :label="__('app.listings.form.fields.description.label')"
                                                  :placeholder="__('app.listings.form.fields.description.placeholder')"
                                                  :hint="__('app.listings.form.fields.description.hint', ['max' => 5000])"
                                                  maxlength="5000" />
                            </div>
                            <x-listings.photo-input class="md:col-span-2 lg:col-span-4" />
                        </x-listings.form-section>
                    </div>

                    <div class="hidden justify-end gap-3 border-t border-rule pt-6 md:flex">
                        <x-ui.button variant="ghost" :href="$backUrl">{{ __('app.listings.create.cancel') }}</x-ui.button>
                        <x-ui.button variant="primary" type="submit">{{ __('app.listings.create.submit') }}</x-ui.button>
                    </div>

                    <div data-sticky-bar class="sticky bottom-(--cookie-banner-h,0px) z-10 -mx-5 mt-5 -mb-6 border-t border-rule bg-paper p-5 md:hidden">
                        <x-ui.button variant="primary" type="submit" size="lg" class="w-full">{{ __('app.listings.create.submit') }}</x-ui.button>
                    </div>
                </form>
            </div>
        </div>
    </section>
</x-layouts.app>
