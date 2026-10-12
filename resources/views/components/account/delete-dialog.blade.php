@props(['action'])

<div {{ $attributes->merge(['class' => 'w-full md:ml-auto md:w-auto']) }}>
    <x-ui.button variant="destructive" size="responsive" class="w-full md:w-auto"
                 data-dialog-open="delete-account" commandfor="delete-account" command="show-modal">
        {{ __('app.profile.delete.open') }}
    </x-ui.button>

    <x-ui.dialog id="delete-account" :title="__('app.profile.delete.title')">
        <form method="POST" action="{{ $action }}" class="mt-5 flex flex-col gap-5">
            @csrf
            @method('DELETE')
            <x-forms.required-note />
            <x-forms.field name="delete_password" type="password" autocomplete="current-password" required
                           :label="__('app.profile.delete.password')" :placeholder="__('app.profile.delete.password_placeholder')" />
            <div class="flex flex-col-reverse gap-3 md:flex-row md:justify-end">
                <x-ui.button variant="secondary" size="responsive" class="w-full md:w-auto"
                             data-dialog-close commandfor="delete-account" command="close">
                    {{ __('app.profile.delete.cancel') }}
                </x-ui.button>
                <x-ui.button type="submit" variant="destructive" size="responsive" class="w-full md:w-auto">
                    {{ __('app.profile.delete.confirm') }}
                </x-ui.button>
            </div>
        </form>
    </x-ui.dialog>
</div>
