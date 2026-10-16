<tr class="block border-t border-rule px-4 py-3 lg:table-row lg:p-0">
    <td class="flex gap-3 py-1 lg:table-cell lg:px-3 lg:py-3 lg:align-middle">
        <span class="w-32 shrink-0 text-[11px] tracking-[0.12em] text-muted uppercase lg:hidden">{{ __('app.admin.makers.columns.name') }}</span>
        <a href="{{ $showUrl() }}" class="inline-flex min-h-11 min-w-0 items-center font-heading text-lg text-red italic wrap-break-word underline-offset-4 hover:underline lg:min-h-0">{{ $maker->name }}</a>
    </td>
    <td class="flex gap-3 py-1 tabular-nums lg:table-cell lg:px-3 lg:py-3 lg:align-middle">
        <span class="w-32 shrink-0 text-[11px] tracking-[0.12em] text-muted uppercase lg:hidden">{{ __('app.admin.makers.columns.models') }}</span>
        {{ $maker->models_count }}
    </td>
    <td class="flex gap-3 py-1 tabular-nums lg:table-cell lg:px-3 lg:py-3 lg:align-middle">
        <span class="w-32 shrink-0 text-[11px] tracking-[0.12em] text-muted uppercase lg:hidden">{{ __('app.admin.makers.columns.listings') }}</span>
        {{ $maker->listings_count }}
    </td>
    <td class="flex items-center gap-3 py-1 lg:table-cell lg:px-3 lg:py-3 lg:align-middle">
        <span class="w-32 shrink-0 text-[11px] tracking-[0.12em] text-muted uppercase lg:hidden">{{ __('app.admin.makers.columns.status') }}</span>
        @if ($maker->is_active)
            <span class="text-ink-2">{{ __('app.admin.makers.status.active') }}</span>
        @else
            <x-ui.stamp tone="status-info">
                <span class="block px-2 py-0.5 text-[11px] tracking-[0.16em] uppercase">{{ __('app.admin.makers.status.inactive') }}</span>
            </x-ui.stamp>
        @endif
    </td>
    <td class="flex flex-wrap items-center gap-2 pt-2 lg:table-cell lg:px-3 lg:py-3 lg:align-middle">
        <div class="flex flex-wrap items-center gap-2">
            <x-ui.button variant="secondary" size="responsive"
                         data-dialog-open="{{ $editDialogId() }}" commandfor="{{ $editDialogId() }}" command="show-modal">
                {{ __('app.admin.makers.actions.edit') }}<span class="sr-only"> — {{ $maker->name }}</span>
            </x-ui.button>

            <form method="POST" action="{{ $activeUrl() }}">
                @csrf
                @method('PATCH')
                @if ($maker->is_active)
                    <input type="hidden" name="active" value="0">
                    <x-ui.button type="submit" variant="secondary" size="responsive">
                        {{ __('app.admin.makers.actions.deactivate') }}<span class="sr-only"> — {{ $maker->name }}</span>
                    </x-ui.button>
                @else
                    <input type="hidden" name="active" value="1">
                    <x-ui.button type="submit" variant="primary" size="responsive">
                        {{ __('app.admin.makers.actions.activate') }}<span class="sr-only"> — {{ $maker->name }}</span>
                    </x-ui.button>
                @endif
            </form>

            @if ($deletable())
                <x-ui.button variant="destructive" size="responsive"
                             data-dialog-open="{{ $deleteDialogId() }}" commandfor="{{ $deleteDialogId() }}" command="show-modal">
                    {{ __('app.admin.makers.actions.delete') }}<span class="sr-only"> — {{ $maker->name }}</span>
                </x-ui.button>
                <x-admin.reference-delete-dialog :id="$deleteDialogId()" :action="$destroyUrl()" :name="$maker->name"
                                                 :confirm="__('app.admin.makers.delete.confirm')" />
            @endif
        </div>

        <x-ui.dialog :id="$editDialogId()" :title="__('app.admin.makers.edit.title', ['name' => $maker->name])" :data-dialog-autoopen="$reopensEdit()">
            <form method="POST" action="{{ $updateUrl() }}" class="mt-5 flex flex-col gap-4">
                @csrf
                @method('PATCH')
                <x-forms.required-note />
                <x-forms.field name="name" :id="$editDialogId().'-name'" :bag="$editBag()" :value="$maker->name"
                               :label="__('app.admin.makers.add.name_label')" :hint="__('app.admin.makers.add.name_hint')"
                               required maxlength="64" />
                <div class="flex flex-col-reverse gap-3 md:flex-row md:justify-end">
                    <x-ui.button variant="secondary" size="responsive" class="w-full md:w-auto"
                                 data-dialog-close commandfor="{{ $editDialogId() }}" command="close">
                        {{ __('app.admin.makers.edit.cancel') }}
                    </x-ui.button>
                    <x-ui.button type="submit" variant="primary" size="responsive" class="w-full md:w-auto">
                        {{ __('app.admin.makers.edit.save') }}
                    </x-ui.button>
                </div>
            </form>
        </x-ui.dialog>
    </td>
</tr>
