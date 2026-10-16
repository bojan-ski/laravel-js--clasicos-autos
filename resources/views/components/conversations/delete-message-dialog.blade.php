@props(['id', 'action', 'time' => null])

<div {{ $attributes }}>
    <button type="button" class="inline-flex min-h-11 items-center text-red-deep md:min-h-0"
            data-dialog-open="{{ $id }}" commandfor="{{ $id }}" command="show-modal">
        {{ __('app.conversations.delete.open') }}@if ($time !== null)<span class="sr-only"> {{ __('app.conversations.delete.context', ['time' => $time]) }}</span>@else<span class="sr-only" data-delete-context data-template=" {{ __('app.conversations.delete.context') }}"></span>@endif
    </button>

    <x-ui.dialog :id="$id" :title="__('app.conversations.delete.title')">
        <p class="mt-3 text-sm text-ink-2">{{ __('app.conversations.delete.warning') }}</p>
        <form method="POST" action="{{ $action }}" class="mt-5 flex flex-col-reverse gap-3 md:flex-row md:justify-end">
            @csrf
            @method('DELETE')
            <x-ui.button variant="secondary" size="responsive" class="w-full md:w-auto"
                         data-dialog-close commandfor="{{ $id }}" command="close">
                {{ __('app.conversations.delete.cancel') }}
            </x-ui.button>
            <x-ui.button type="submit" variant="destructive" size="responsive" class="w-full md:w-auto">
                {{ __('app.conversations.delete.confirm') }}
            </x-ui.button>
        </form>
    </x-ui.dialog>
</div>
