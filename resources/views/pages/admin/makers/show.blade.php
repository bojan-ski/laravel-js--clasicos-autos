<x-layouts.admin :title="$maker->name">
    <x-ui.back-link :href="route('admin.makers')" :label="__('app.admin.models.back')" />

    <div class="mx-auto flex max-w-7xl flex-col gap-10 px-5 py-6 md:px-14 md:py-16">
        <div class="flex flex-col gap-3">
            <x-ui.page-header :kicker="__('app.admin.models.kicker')" kicker-lang="es" :title="$maker->name" class="wrap-break-word" />
            @if (! $maker->is_active)
                <div class="flex flex-col items-start gap-2">
                    <x-ui.stamp tone="status-info">
                        <span class="block px-2 py-0.5 text-[11px] tracking-[0.16em] uppercase">{{ __('app.admin.makers.status.inactive') }}</span>
                    </x-ui.stamp>
                    <p class="text-sm text-ink-2">{{ __('app.admin.models.inactive_maker') }}</p>
                </div>
            @endif
        </div>

        <section aria-labelledby="add-model-title" class="flex flex-col gap-4.5 border border-rule bg-card p-6 md:p-8">
            <h2 id="add-model-title" class="sr-only">{{ __('app.admin.models.add.title') }}</h2>
            <x-forms.error-summary />
            <form method="POST" action="{{ route('admin.makers.models.store', $maker) }}" class="flex flex-col gap-4.5">
                @csrf
                <x-forms.required-note />
                <div class="flex flex-col gap-4.5 md:flex-row md:items-end">
                    <div class="md:flex-1">
                        <x-forms.field name="name" bag="default" :label="__('app.admin.models.add.name_label')"
                                       :hint="__('app.admin.models.add.name_hint')" required maxlength="64" />
                    </div>
                    <div class="md:w-36">
                        <x-forms.field name="year_from" bag="default" type="number" inputmode="numeric"
                                       :min="config('listings.min_year')" :max="config('listings.max_year')"
                                       :label="__('app.admin.models.add.year_from_label')" :hint="__('app.admin.models.add.years_hint', ['min' => config('listings.min_year'), 'max' => config('listings.max_year')])"
                                       required />
                    </div>
                    <div class="md:w-36">
                        <x-forms.field name="year_to" bag="default" type="number" inputmode="numeric"
                                       :min="config('listings.min_year')" :max="config('listings.max_year')"
                                       :label="__('app.admin.models.add.year_to_label')" :hint="__('app.admin.models.add.years_hint', ['min' => config('listings.min_year'), 'max' => config('listings.max_year')])"
                                       required />
                    </div>
                    <x-ui.button type="submit" variant="primary" size="responsive">{{ __('app.admin.models.add.submit') }}</x-ui.button>
                </div>
            </form>
        </section>

        <div class="border border-rule bg-card">
            @if ($models->isEmpty())
                <p class="px-4 py-6 text-sm text-ink-2 lg:px-3">{{ __('app.admin.models.empty') }}</p>
            @else
                <table class="block w-full text-left text-sm text-ink lg:table">
                    <thead class="hidden lg:table-header-group">
                        <tr class="text-[11px] tracking-[0.12em] text-muted uppercase">
                            <th scope="col" class="px-3 py-3 font-normal">{{ __('app.admin.models.columns.name') }}</th>
                            <th scope="col" class="px-3 py-3 font-normal">{{ __('app.admin.models.columns.years') }}</th>
                            <th scope="col" class="px-3 py-3 font-normal">{{ __('app.admin.models.columns.listings') }}</th>
                            <th scope="col" class="px-3 py-3 font-normal">{{ __('app.admin.models.columns.status') }}</th>
                            <th scope="col" class="px-3 py-3 font-normal"><span class="sr-only">{{ __('app.admin.models.columns.actions') }}</span></th>
                        </tr>
                    </thead>
                    <tbody class="block lg:table-row-group">
                        @foreach ($models as $model)
                            <x-admin.model-row :maker="$maker" :model="$model" />
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>
</x-layouts.admin>
