@props(['id', 'action', 'describedby'])

<div {{ $attributes }}>
    <button type="button" class="inline-flex min-h-11 items-center gap-1 text-ink-2 hover:text-ink md:min-h-0" aria-describedby="{{ $describedby }}"
            data-dialog-open="{{ $id }}" commandfor="{{ $id }}" command="show-modal">
        <x-icons.flag class="size-3.5" />{{ __('app.conversations.report.open') }}
    </button>

    <x-ui.dialog :id="$id" :title="__('app.conversations.report.title')">
        <form method="POST" action="{{ $action }}" class="mt-5 flex flex-col gap-4">
            @csrf
            <x-forms.required-note />
            <x-forms.select name="reason" :id="$id.'-reason'" :label="__('app.conversations.report.reason_label')"
                            :options="\App\Enums\ReportReason::options()" :placeholder="__('app.conversations.report.reason_placeholder')" required />
            <x-forms.textarea name="note" :id="$id.'-note'" :label="__('app.conversations.report.note_label')"
                              :hint="__('app.conversations.report.note_hint')" maxlength="500" />
            <p class="text-sm text-ink-2">{{ __('app.conversations.report.stays_visible') }}</p>
            <div class="flex flex-col-reverse gap-3 md:flex-row md:justify-end">
                <x-ui.button variant="secondary" size="responsive" class="w-full md:w-auto"
                             data-dialog-close commandfor="{{ $id }}" command="close">
                    {{ __('app.conversations.report.cancel') }}
                </x-ui.button>
                <x-ui.button type="submit" variant="primary" size="responsive" class="w-full md:w-auto">
                    {{ __('app.conversations.report.confirm') }}
                </x-ui.button>
            </div>
        </form>
    </x-ui.dialog>
</div>
