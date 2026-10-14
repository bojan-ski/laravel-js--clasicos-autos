@props(['action'])

<div {{ $attributes }}>
    <x-ui.button variant="destructive" size="responsive"
                 data-dialog-open="delete-listing" commandfor="delete-listing" command="show-modal">
        {{ __('app.listings.edit.delete.open') }}
    </x-ui.button>

    <x-ui.dialog id="delete-listing" :title="__('app.listings.edit.delete.title')">
        <form method="POST" action="{{ $action }}" class="mt-5 flex flex-col-reverse gap-3 md:flex-row md:justify-end">
            @csrf
            @method('DELETE')
            <x-ui.button variant="secondary" size="responsive" class="w-full md:w-auto"
                         data-dialog-close commandfor="delete-listing" command="close">
                {{ __('app.listings.edit.delete.cancel') }}
            </x-ui.button>
            <x-ui.button type="submit" variant="destructive" size="responsive" class="w-full md:w-auto">
                {{ __('app.listings.edit.delete.confirm') }}
            </x-ui.button>
        </form>
    </x-ui.dialog>
</div>
