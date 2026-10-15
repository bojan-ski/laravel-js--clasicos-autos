@props(['search', 'filter'])

<form method="GET" action="{{ route('admin.listings') }}" role="search" {{ $attributes->merge(['class' => 'flex w-full flex-col gap-2 md:w-auto md:flex-row md:items-end']) }}>
    @if ($filter->queryValue() !== null)
        <input type="hidden" name="status" value="{{ $filter->queryValue() }}">
    @endif
    <x-forms.field name="search" type="search" maxlength="100" class="md:w-80"
                   :label="__('app.admin.listings.search_label')" :placeholder="__('app.admin.listings.search_placeholder')" :value="$search" />
    <x-ui.button type="submit" variant="primary" size="responsive">{{ __('app.admin.listings.search_button') }}</x-ui.button>
</form>
