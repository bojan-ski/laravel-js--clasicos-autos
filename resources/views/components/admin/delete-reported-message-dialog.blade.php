@props(['id', 'action', 'username'])

<x-ui.dialog :id="$id" :title="__('app.admin.reports.delete.title', ['username' => $username])">
    <p class="mt-3 text-sm text-ink-2">{{ __('app.admin.reports.delete.warning') }}</p>
    <form method="POST" action="{{ $action }}" class="mt-5 flex flex-col-reverse gap-3 md:flex-row md:justify-end">
        @csrf
        @method('PATCH')
        <input type="hidden" name="outcome" value="{{ \App\Enums\ReportOutcome::MessageDeleted->value }}">
        <x-ui.button variant="secondary" size="responsive" class="w-full md:w-auto"
                     data-dialog-close commandfor="{{ $id }}" command="close">
            {{ __('app.admin.reports.delete.cancel') }}
        </x-ui.button>
        <x-ui.button type="submit" variant="destructive" size="responsive" class="w-full md:w-auto">
            {{ __('app.admin.reports.delete.confirm') }}
        </x-ui.button>
    </form>
</x-ui.dialog>
