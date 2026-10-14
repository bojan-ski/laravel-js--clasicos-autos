@props(['action', 'version'])

<div {{ $attributes }}>
    <x-ui.button variant="destructive" size="responsive" class="w-full md:w-auto"
                 data-dialog-open="reject-listing" commandfor="reject-listing" command="show-modal">
        {{ __('app.listings.moderation.reject') }}
    </x-ui.button>

    <x-ui.dialog id="reject-listing" :title="__('app.listings.moderation.reject_title')">
        <form method="POST" action="{{ $action }}" class="mt-5 flex flex-col gap-4">
            @csrf
            @method('PATCH')
            <input type="hidden" name="version" value="{{ $version }}">
            <x-forms.required-note />
            <x-forms.textarea name="rejection_reason" :label="__('app.listings.moderation.reason_label')"
                              :hint="__('app.listings.moderation.reason_hint')" required maxlength="1000" />
            <div class="flex flex-col-reverse gap-3 md:flex-row md:justify-end">
                <x-ui.button variant="secondary" size="responsive" class="w-full md:w-auto"
                             data-dialog-close commandfor="reject-listing" command="close">
                    {{ __('app.listings.moderation.cancel') }}
                </x-ui.button>
                <x-ui.button type="submit" variant="destructive" size="responsive" class="w-full md:w-auto">
                    {{ __('app.listings.moderation.confirm_reject') }}
                </x-ui.button>
            </div>
        </form>
    </x-ui.dialog>
</div>
