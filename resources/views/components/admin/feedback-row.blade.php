<tr class="block border-t border-rule px-4 py-3 lg:table-row lg:p-0">
    <td class="flex gap-3 py-1 lg:table-cell lg:px-3 lg:py-3 lg:align-top">
        <span class="w-32 shrink-0 text-[11px] tracking-[0.12em] text-muted uppercase lg:hidden">{{ __('app.admin.feedback.columns.sender') }}</span>
        <span class="flex min-w-0 flex-col items-start gap-0.5">
            <span class="wrap-break-word">{{ $contactMessage->user->username }}</span>
            <a href="{{ $mailto() }}" class="inline-flex min-h-11 items-center text-xs text-ink underline underline-offset-4 wrap-break-word hover:no-underline md:min-h-0">{{ $contactMessage->user->email }}</a>
            <span class="text-xs text-ink-2">{{ $contactMessage->user->phone_number }}</span>
            @if ($senderStatus() !== null)
                <span class="text-xs text-red-deep">{{ $senderStatus() }}</span>
            @endif
        </span>
    </td>
    <td class="flex gap-3 py-1 lg:table-cell lg:px-3 lg:py-3 lg:align-top">
        <span class="w-32 shrink-0 text-[11px] tracking-[0.12em] text-muted uppercase lg:hidden">{{ __('app.admin.feedback.columns.subject') }}</span>
        <span class="min-w-0 font-heading text-ink italic wrap-break-word">{{ $contactMessage->subject }}</span>
    </td>
    <td class="flex gap-3 py-1 lg:table-cell lg:px-3 lg:py-3 lg:align-top">
        <span class="w-32 shrink-0 text-[11px] tracking-[0.12em] text-muted uppercase lg:hidden">{{ __('app.admin.feedback.columns.message') }}</span>
        <span class="min-w-0 whitespace-pre-line wrap-break-word">{{ $contactMessage->message }}</span>
    </td>
    <td class="flex gap-3 py-1 tabular-nums lg:table-cell lg:px-3 lg:py-3 lg:align-top">
        <span class="w-32 shrink-0 text-[11px] tracking-[0.12em] text-muted uppercase lg:hidden">{{ __('app.admin.feedback.columns.received') }}</span>
        <time datetime="{{ $receivedIso() }}">{{ $receivedAt() }}</time>
    </td>
    <td class="flex gap-3 py-1 lg:table-cell lg:px-3 lg:py-3 lg:align-top">
        <span class="w-32 shrink-0 text-[11px] tracking-[0.12em] text-muted uppercase lg:hidden">{{ __('app.admin.feedback.columns.status') }}</span>
        <span class="flex min-w-0 flex-col items-start gap-1">
            <x-ui.stamp :tone="$statusTone()">
                <span class="block px-2 py-0.5 text-[11px] tracking-[0.16em] uppercase">{{ $contactMessage->status->label() }}</span>
            </x-ui.stamp>
            @if (! $isNew())
                <span class="text-xs text-ink-2">{{ $handlerName() }} · <time datetime="{{ $handledIso() }}" class="tabular-nums">{{ $handledAt() }}</time></span>
            @endif
        </span>
    </td>
    <td class="flex flex-wrap items-center gap-2 pt-2 lg:table-cell lg:px-3 lg:py-3 lg:align-top">
        @can('updateStatus', $contactMessage)
            <form method="POST" action="{{ $actionUrl() }}">
                @csrf
                @method('PATCH')
                <input type="hidden" name="target_status" value="{{ $actionTarget()->value }}">
                <x-ui.button type="submit" :variant="$actionVariant()" size="responsive">
                    {{ $actionLabel() }}<span class="sr-only"> {{ $actionContext() }}</span>
                </x-ui.button>
            </form>
        @endcan
    </td>
</tr>
