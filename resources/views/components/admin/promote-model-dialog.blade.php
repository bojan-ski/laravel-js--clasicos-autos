@props(['listing', 'action', 'id', 'version'])

<x-ui.button variant="secondary" size="responsive"
             data-dialog-open="{{ $id }}" commandfor="{{ $id }}" command="show-modal">
    {{ __('app.admin.listings.promote.open') }}<span class="sr-only"> {{ $listing->name }}</span>
</x-ui.button>

<x-ui.dialog :id="$id" :title="__('app.admin.listings.promote.title')">
    <form method="POST" action="{{ $action }}" class="mt-5 flex flex-col gap-4">
        @csrf
        @method('PATCH')
        <input type="hidden" name="version" value="{{ $version }}">
        <x-forms.required-note />
        <x-forms.field name="name" :id="$id.'-name'" :label="__('app.admin.listings.promote.name_label')"
                       :hint="__('app.admin.listings.promote.name_hint', ['maker' => $listing->maker->name])"
                       :value="$listing->model" required maxlength="64" />
        <div class="grid gap-4 md:grid-cols-2">
            <x-forms.field name="year_from" :id="$id.'-year-from'" type="number" inputmode="numeric"
                           :label="__('app.admin.listings.promote.year_from_label')" :value="$listing->year"
                           :min="config('listings.min_year')" :max="config('listings.max_year')"
                           aria-describedby="{{ $id }}-years-hint" required />
            <x-forms.field name="year_to" :id="$id.'-year-to'" type="number" inputmode="numeric"
                           :label="__('app.admin.listings.promote.year_to_label')" :value="$listing->year"
                           :min="config('listings.min_year')" :max="config('listings.max_year')"
                           aria-describedby="{{ $id }}-years-hint" required />
        </div>
        <p id="{{ $id }}-years-hint" class="-mt-2 text-xs text-muted">{{ __('app.admin.listings.promote.years_hint', ['year' => $listing->year]) }}</p>
        <div class="flex flex-col-reverse gap-3 md:flex-row md:justify-end">
            <x-ui.button variant="secondary" size="responsive" class="w-full md:w-auto"
                         data-dialog-close commandfor="{{ $id }}" command="close">
                {{ __('app.admin.listings.promote.cancel') }}
            </x-ui.button>
            <x-ui.button type="submit" variant="primary" size="responsive" class="w-full md:w-auto">
                {{ __('app.admin.listings.promote.confirm') }}
            </x-ui.button>
        </div>
    </form>
</x-ui.dialog>
