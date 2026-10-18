@props(['action', 'version', 'id' => 'reject-listing', 'fieldId' => 'rejection_reason', 'context' => null])

<div {{ $attributes }}>
    <x-ui.button variant="destructive" size="responsive" class="w-full md:w-auto"
                 data-dialog-open="{{ $id }}" commandfor="{{ $id }}" command="show-modal">
        {{ __('app.listings.moderation.reject') }}@if ($context !== null)<span class="sr-only"> {{ $context }}</span>@endif
    </x-ui.button>

    <x-ui.dialog :id="$id" :title="__('app.listings.moderation.reject_title')">
        <form method="POST" action="{{ $action }}" class="mt-5 flex flex-col gap-4">
            @csrf
            @method('PATCH')
            <input type="hidden" name="version" value="{{ $version }}">
            <x-forms.required-note />
            <x-forms.textarea name="rejection_reason" :id="$fieldId" :label="__('app.listings.moderation.reason_label')"
                              :hint="__('app.listings.moderation.reason_hint', ['max' => \App\Http\Requests\Admin\RejectListingRequest::REASON_MAX])" required
                              :maxlength="\App\Http\Requests\Admin\RejectListingRequest::REASON_MAX" />
            <div class="flex flex-col-reverse gap-3 md:flex-row md:justify-end">
                <x-ui.button variant="secondary" size="responsive" class="w-full md:w-auto"
                             data-dialog-close commandfor="{{ $id }}" command="close">
                    {{ __('app.listings.moderation.cancel') }}
                </x-ui.button>
                <x-ui.button type="submit" variant="destructive" size="responsive" class="w-full md:w-auto">
                    {{ __('app.listings.moderation.confirm_reject') }}
                </x-ui.button>
            </div>
        </form>
    </x-ui.dialog>
</div>
