@props(['search'])

<form method="GET" action="{{ route('admin.users') }}" role="search" {{ $attributes->merge(['class' => 'flex w-full flex-col gap-2 md:w-auto md:flex-row md:items-end']) }}>
    <x-forms.field name="search" type="search" maxlength="100" class="md:w-80"
                   :label="__('app.admin.users.search_label')" :placeholder="__('app.admin.users.search_placeholder')" :value="$search" />
    <x-ui.button type="submit" variant="primary" size="responsive">{{ __('app.admin.users.search_button') }}</x-ui.button>
</form>
