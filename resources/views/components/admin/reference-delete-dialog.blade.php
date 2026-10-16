@props(['id', 'action', 'name', 'confirm'])

<x-ui.dialog :id="$id" :title="__('app.admin.makers.delete.title', ['name' => $name])">
    <form method="POST" action="{{ $action }}" class="mt-5 flex flex-col gap-4">
        @csrf
        @method('DELETE')
        <p class="text-sm text-ink-2">{{ __('app.admin.makers.delete.body') }}</p>
        <div class="flex flex-col-reverse gap-3 md:flex-row md:justify-end">
            <x-ui.button variant="secondary" size="responsive" class="w-full md:w-auto"
                         data-dialog-close commandfor="{{ $id }}" command="close">
                {{ __('app.admin.makers.delete.cancel') }}
            </x-ui.button>
            <x-ui.button type="submit" variant="destructive" size="responsive" class="w-full md:w-auto">
                {{ $confirm }}
            </x-ui.button>
        </div>
    </form>
</x-ui.dialog>
