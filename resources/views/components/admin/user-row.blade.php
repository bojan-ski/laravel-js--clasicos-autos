<tr class="block border-t border-rule px-4 py-3 lg:table-row lg:p-0">
    <td class="flex gap-3 py-1 tabular-nums lg:table-cell lg:px-3 lg:py-3 lg:align-middle">
        <span class="w-32 shrink-0 text-[11px] tracking-[0.12em] text-muted uppercase lg:hidden">{{ __('app.admin.users.columns.id') }}</span>
        {{ $user->id }}
    </td>
    <td class="flex gap-3 py-1 lg:table-cell lg:px-3 lg:py-3 lg:align-middle">
        <span class="w-32 shrink-0 text-[11px] tracking-[0.12em] text-muted uppercase lg:hidden">{{ __('app.admin.users.columns.username') }}</span>
        <span class="min-w-0 wrap-anywhere">{{ $user->username }}</span>
    </td>
    <td class="flex gap-3 py-1 lg:table-cell lg:px-3 lg:py-3 lg:align-middle">
        <span class="w-32 shrink-0 text-[11px] tracking-[0.12em] text-muted uppercase lg:hidden">{{ __('app.admin.users.columns.email') }}</span>
        <span class="min-w-0 wrap-anywhere">{{ $user->email }}</span>
    </td>
    <td class="flex gap-3 py-1 tabular-nums lg:table-cell lg:px-3 lg:py-3 lg:align-middle">
        <span class="w-32 shrink-0 text-[11px] tracking-[0.12em] text-muted uppercase lg:hidden">{{ __('app.admin.users.columns.phone') }}</span>
        {{ $user->phone_number }}
    </td>
    <td class="flex gap-3 py-1 tabular-nums lg:table-cell lg:px-3 lg:py-3 lg:align-middle">
        <span class="w-32 shrink-0 text-[11px] tracking-[0.12em] text-muted uppercase lg:hidden">{{ __('app.admin.users.columns.created') }}</span>
        {{ $created() }}
    </td>
    <td class="flex items-center gap-3 py-1 lg:table-cell lg:px-3 lg:py-3 lg:align-middle">
        <span class="w-32 shrink-0 text-[11px] tracking-[0.12em] text-muted uppercase lg:hidden">{{ __('app.admin.users.columns.status') }}</span>
        @if ($isSuspended())
            <x-ui.stamp tone="status-rejected">
                <span class="block px-2 py-0.5 text-[11px] tracking-[0.16em] uppercase">{{ $status() }}</span>
            </x-ui.stamp>
        @else
            <span class="text-ink-2">{{ $status() }}</span>
        @endif
    </td>
    <td class="flex gap-3 py-1 tabular-nums lg:table-cell lg:px-3 lg:py-3 lg:align-middle">
        <span class="w-32 shrink-0 text-[11px] tracking-[0.12em] text-muted uppercase lg:hidden">{{ __('app.admin.users.columns.listings') }}</span>
        {{ $user->listings_count }}
    </td>
    <td class="flex flex-wrap items-center gap-1 pt-2 lg:table-cell lg:px-3 lg:py-3 lg:align-middle">
        <div class="flex items-center gap-1">
            <a href="{{ $listingsUrl() }}" class="inline-flex size-11 items-center justify-center rounded-xs text-ink hover:bg-red/10 active:bg-red/18 lg:size-9">
                <x-icons.eye class="size-5" />
                <span class="sr-only">{{ __('app.admin.users.actions.view', ['username' => $user->username]) }}</span>
            </a>

            @if ($isSuspended())
                @can('unsuspend', $user)
                    <form method="POST" action="{{ $unsuspendUrl() }}">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="inline-flex size-11 cursor-pointer items-center justify-center rounded-xs text-ink hover:bg-red/10 active:bg-red/18 lg:size-9">
                            <x-icons.rotate-ccw class="size-5" />
                            <span class="sr-only">{{ __('app.admin.users.actions.unsuspend', ['username' => $user->username]) }}</span>
                        </button>
                    </form>
                @endcan
            @else
                @can('suspend', $user)
                    <button type="button" class="inline-flex size-11 cursor-pointer items-center justify-center rounded-xs text-red-deep hover:bg-red/10 active:bg-red/18 lg:size-9"
                            data-dialog-open="suspend-user-{{ $user->id }}" commandfor="suspend-user-{{ $user->id }}" command="show-modal">
                        <x-icons.ban class="size-5" />
                        <span class="sr-only">{{ __('app.admin.users.actions.suspend', ['username' => $user->username]) }}</span>
                    </button>
                    <x-admin.suspend-dialog :user="$user" :action="$suspendUrl()" :options="$durationOptions()" />
                @endcan
            @endif

            @can('delete', $user)
                <button type="button" class="inline-flex size-11 cursor-pointer items-center justify-center rounded-xs text-red-deep hover:bg-red/10 active:bg-red/18 lg:size-9"
                        data-dialog-open="delete-user-{{ $user->id }}" commandfor="delete-user-{{ $user->id }}" command="show-modal">
                    <x-icons.x class="size-5" />
                    <span class="sr-only">{{ __('app.admin.users.actions.delete', ['username' => $user->username]) }}</span>
                </button>
                <x-admin.delete-user-dialog :user="$user" :action="$destroyUrl()" />
            @endcan
        </div>
    </td>
</tr>
