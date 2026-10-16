<x-layouts.admin :title="__('app.admin.makers.title')">
    <div class="mx-auto flex max-w-7xl flex-col gap-10 px-5 py-6 md:px-14 md:py-16">
        <x-ui.page-header :kicker="__('app.admin.makers.kicker')" kicker-lang="es" :title="__('app.admin.makers.title')" />

        <section aria-labelledby="add-maker-title" class="flex flex-col gap-4.5 border border-rule bg-card p-6 md:p-8">
            <h2 id="add-maker-title" class="sr-only">{{ __('app.admin.makers.add.title') }}</h2>
            <x-forms.error-summary />
            <form method="POST" action="{{ route('admin.makers.store') }}" class="flex flex-col gap-4.5">
                @csrf
                <x-forms.required-note />
                <div class="flex flex-col gap-4.5 md:flex-row md:items-end">
                    <div class="md:flex-1">
                        <x-forms.field name="name" bag="default" :label="__('app.admin.makers.add.name_label')"
                                       :hint="__('app.admin.makers.add.name_hint')" required maxlength="64" />
                    </div>
                    <x-ui.button type="submit" variant="primary" size="responsive">{{ __('app.admin.makers.add.submit') }}</x-ui.button>
                </div>
            </form>
        </section>

        <div class="border border-rule bg-card">
            @if ($makers->isEmpty())
                <p class="px-4 py-6 text-sm text-ink-2 lg:px-3">{{ __('app.admin.makers.empty') }}</p>
            @else
                <table class="block w-full text-left text-sm text-ink lg:table">
                    <thead class="hidden lg:table-header-group">
                        <tr class="text-[11px] tracking-[0.12em] text-muted uppercase">
                            <th scope="col" class="px-3 py-3 font-normal">{{ __('app.admin.makers.columns.name') }}</th>
                            <th scope="col" class="px-3 py-3 font-normal">{{ __('app.admin.makers.columns.models') }}</th>
                            <th scope="col" class="px-3 py-3 font-normal">{{ __('app.admin.makers.columns.listings') }}</th>
                            <th scope="col" class="px-3 py-3 font-normal">{{ __('app.admin.makers.columns.status') }}</th>
                            <th scope="col" class="px-3 py-3 font-normal"><span class="sr-only">{{ __('app.admin.makers.columns.actions') }}</span></th>
                        </tr>
                    </thead>
                    <tbody class="block lg:table-row-group">
                        @foreach ($makers as $maker)
                            <x-admin.maker-row :maker="$maker" />
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>
</x-layouts.admin>
