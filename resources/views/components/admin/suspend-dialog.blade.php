@props(['user', 'action', 'options', 'id' => null])

@php($dialogId = $id ?? 'suspend-user-'.$user->id)

<x-ui.dialog :id="$dialogId" :title="__('app.admin.users.suspend.title', ['username' => $user->username])">
    <form method="POST" action="{{ $action }}" class="mt-5 flex flex-col gap-4">
        @csrf
        @method('PATCH')
        {{ $slot }}
        <x-forms.required-note />
        <x-forms.textarea name="reason" :id="$dialogId.'-reason'" :label="__('app.admin.users.suspend.reason_label')"
                          :hint="__('app.admin.users.suspend.reason_hint')" required maxlength="500" />
        <x-forms.select name="duration" :id="$dialogId.'-duration'" :label="__('app.admin.users.suspend.duration_label')"
                        :options="$options" :placeholder="__('app.admin.users.suspend.duration_placeholder')" required />
        <div class="flex flex-col-reverse gap-3 md:flex-row md:justify-end">
            <x-ui.button variant="secondary" size="responsive" class="w-full md:w-auto"
                         data-dialog-close commandfor="{{ $dialogId }}" command="close">
                {{ __('app.admin.users.suspend.cancel') }}
            </x-ui.button>
            <x-ui.button type="submit" variant="destructive" size="responsive" class="w-full md:w-auto">
                {{ __('app.admin.users.suspend.confirm') }}
            </x-ui.button>
        </div>
    </form>
</x-ui.dialog>
