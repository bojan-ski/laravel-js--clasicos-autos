<tr class="block border-t border-rule px-4 py-3 lg:table-row lg:p-0">
    <td class="flex gap-3 py-1 lg:table-cell lg:px-3 lg:py-3 lg:align-top">
        <span class="w-32 shrink-0 text-[11px] tracking-[0.12em] text-muted uppercase lg:hidden">{{ __('app.admin.reports.columns.message') }}</span>
        <span class="flex min-w-0 flex-col items-start gap-0.5">
            <span class="text-ink italic wrap-break-word">{{ $report->message_text }}</span>
            @if ($isMessageDeleted())
                <span class="text-[11px] tracking-[0.12em] text-ink-2 uppercase">{{ __('app.admin.reports.message_deleted') }}</span>
            @endif
            <a href="{{ $conversationUrl() }}" class="inline-flex min-h-11 items-center text-xs text-ink underline underline-offset-4 hover:no-underline md:min-h-0">{{ __('app.admin.reports.open_conversation') }}</a>
        </span>
    </td>
    <td class="flex gap-3 py-1 lg:table-cell lg:px-3 lg:py-3 lg:align-top">
        <span class="w-32 shrink-0 text-[11px] tracking-[0.12em] text-muted uppercase lg:hidden">{{ __('app.admin.reports.columns.reason') }}</span>
        <span class="flex min-w-0 flex-col gap-0.5">
            <span>{{ $report->reason->label() }}</span>
            @if ($report->note !== null)
                <span class="text-xs text-ink-2 wrap-break-word">{{ $report->note }}</span>
            @else
                <span class="text-xs text-muted">{{ __('app.admin.reports.no_note') }}</span>
            @endif
        </span>
    </td>
    <td class="flex gap-3 py-1 lg:table-cell lg:px-3 lg:py-3 lg:align-top">
        <span class="w-32 shrink-0 text-[11px] tracking-[0.12em] text-muted uppercase lg:hidden">{{ __('app.admin.reports.columns.reporter') }}</span>
        <span class="min-w-0 wrap-break-word">{{ $report->reporter->username }}</span>
    </td>
    <td class="flex gap-3 py-1 lg:table-cell lg:px-3 lg:py-3 lg:align-top">
        <span class="w-32 shrink-0 text-[11px] tracking-[0.12em] text-muted uppercase lg:hidden">{{ __('app.admin.reports.columns.reported_user') }}</span>
        <span class="flex min-w-0 flex-col gap-0.5">
            <span class="wrap-break-word">{{ $report->reportedUser->username }}</span>
            @if ($reportedUserStatus() !== null)
                <span class="text-xs text-red-deep">{{ $reportedUserStatus() }}</span>
            @endif
        </span>
    </td>
    <td class="flex gap-3 py-1 tabular-nums lg:table-cell lg:px-3 lg:py-3 lg:align-top">
        <span class="w-32 shrink-0 text-[11px] tracking-[0.12em] text-muted uppercase lg:hidden">{{ __('app.admin.reports.columns.reported') }}</span>
        <time datetime="{{ $report->created_at->toIso8601String() }}">{{ $reportedAt() }}</time>
    </td>
    <td class="flex gap-3 py-1 lg:table-cell lg:px-3 lg:py-3 lg:align-top">
        <span class="w-32 shrink-0 text-[11px] tracking-[0.12em] text-muted uppercase lg:hidden">{{ __('app.admin.reports.columns.status') }}</span>
        <span class="flex min-w-0 flex-col items-start gap-1">
            <x-ui.stamp :tone="$statusTone()">
                <span class="block px-2 py-0.5 text-[11px] tracking-[0.16em] uppercase">{{ $report->status->label() }}</span>
            </x-ui.stamp>
            @if (! $showsActions())
                <span class="text-xs text-ink-2">{{ $report->outcome->label() }} · {{ $resolverName() }}</span>
                <span class="text-xs text-ink-2 tabular-nums">{{ $resolvedAt() }}</span>
            @endif
        </span>
    </td>
    <td class="flex flex-wrap items-center gap-2 pt-2 lg:table-cell lg:px-3 lg:py-3 lg:align-top">
        @if ($showsActions())
            @can('resolve', $report)
                <div class="flex flex-wrap items-center gap-2">
                    <form method="POST" action="{{ $resolveUrl() }}">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="outcome" value="{{ \App\Enums\ReportOutcome::Dismissed->value }}">
                        <x-ui.button type="submit" variant="secondary" size="responsive">
                            {{ __('app.admin.reports.actions.dismiss') }}<span class="sr-only"> {{ $actionContext() }}</span>
                        </x-ui.button>
                    </form>
                    @if ($offersDeleteMessage())
                        <x-ui.button variant="destructive" size="responsive"
                                     data-dialog-open="{{ $deleteDialogId() }}" commandfor="{{ $deleteDialogId() }}" command="show-modal">
                            {{ __('app.admin.reports.actions.delete_message') }}<span class="sr-only"> {{ $actionContext() }}</span>
                        </x-ui.button>
                        <x-admin.delete-reported-message-dialog :id="$deleteDialogId()" :action="$resolveUrl()" :username="$report->reportedUser->username" />
                    @endif
                    @if ($offersSuspend())
                        <x-ui.button variant="destructive" size="responsive"
                                     data-dialog-open="{{ $suspendDialogId() }}" commandfor="{{ $suspendDialogId() }}" command="show-modal">
                            <x-icons.ban class="size-4" />{{ __('app.admin.reports.actions.suspend') }}<span class="sr-only"> {{ $actionContext() }}</span>
                        </x-ui.button>
                        <x-admin.suspend-dialog :user="$report->reportedUser" :action="$resolveUrl()" :options="$durationOptions()" :id="$suspendDialogId()">
                            <input type="hidden" name="outcome" value="{{ \App\Enums\ReportOutcome::UserSuspended->value }}">
                        </x-admin.suspend-dialog>
                    @endif
                </div>
            @endcan
        @endif
    </td>
</tr>
