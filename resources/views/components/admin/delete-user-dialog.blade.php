@props(['user', 'action'])

<x-ui.dialog id="delete-user-{{ $user->id }}" :title="__('app.admin.users.delete.title', ['username' => $user->username])">
    <form method="POST" action="{{ $action }}" class="mt-5 flex flex-col gap-4">
        @csrf
        @method('DELETE')
        <p class="text-sm text-ink-2">{{ __('app.admin.users.delete.warning') }}</p>
        <div class="flex flex-col-reverse gap-3 md:flex-row md:justify-end">
            <x-ui.button variant="secondary" size="responsive" class="w-full md:w-auto"
                         data-dialog-close commandfor="delete-user-{{ $user->id }}" command="close">
                {{ __('app.admin.users.delete.cancel') }}
            </x-ui.button>
            <x-ui.button type="submit" variant="destructive" size="responsive" class="w-full md:w-auto">
                {{ __('app.admin.users.delete.confirm') }}
            </x-ui.button>
        </div>
    </form>
</x-ui.dialog>
