<tr class="block border-t border-rule px-4 py-3 lg:table-row lg:p-0">
    <td class="flex gap-3 py-1 lg:table-cell lg:px-3 lg:py-3 lg:align-middle">
        <span class="w-32 shrink-0 text-[11px] tracking-[0.12em] text-muted uppercase lg:hidden">{{ __('app.admin.listings.columns.photo') }}</span>
        @if ($thumbUrl() !== null)
            <img src="{{ $thumbUrl() }}" alt="" width="320" height="240" loading="lazy" class="h-15 w-20 object-cover photo-grade">
        @else
            <span class="flex h-15 w-20 items-center justify-center border border-rule bg-paper text-muted">
                <x-icons.image class="size-6" />
            </span>
        @endif
    </td>
    <td class="flex gap-3 py-1 lg:table-cell lg:px-3 lg:py-3 lg:align-middle">
        <span class="w-32 shrink-0 text-[11px] tracking-[0.12em] text-muted uppercase lg:hidden">{{ __('app.admin.listings.columns.listing') }}</span>
        <span class="flex min-w-0 flex-col gap-0.5">
            <a href="{{ route('listings.show', $listing) }}" class="font-heading text-red italic wrap-anywhere hover:text-red-deep">{{ $listing->name }}</a>
            <span class="text-xs text-ink-2 wrap-anywhere">{{ $makerAndModel() }}</span>
            @if ($listing->hasCustomModel())
                <span class="text-[11px] tracking-[0.12em] text-ink-2 uppercase">{{ __('app.admin.listings.custom_model') }}</span>
            @endif
        </span>
    </td>
    <td class="flex gap-3 py-1 tabular-nums lg:table-cell lg:px-3 lg:py-3 lg:align-middle">
        <span class="w-32 shrink-0 text-[11px] tracking-[0.12em] text-muted uppercase lg:hidden">{{ __('app.admin.listings.columns.year') }}</span>
        {{ $listing->year }}
    </td>
    <td class="flex gap-3 py-1 lg:table-cell lg:px-3 lg:py-3 lg:align-middle">
        <span class="w-32 shrink-0 text-[11px] tracking-[0.12em] text-muted uppercase lg:hidden">{{ __('app.admin.listings.columns.owner') }}</span>
        <span class="flex min-w-0 flex-col gap-0.5">
            <a href="{{ route('users.listings', $listing->owner) }}" class="text-ink underline underline-offset-4 wrap-anywhere hover:no-underline">{{ $listing->owner->username }}</a>
            @if ($ownerStatus() !== null)
                <span class="text-xs text-red-deep">{{ $ownerStatus() }}</span>
            @endif
        </span>
    </td>
    <td class="flex gap-3 py-1 tabular-nums lg:table-cell lg:px-3 lg:py-3 lg:align-middle">
        <span class="w-32 shrink-0 text-[11px] tracking-[0.12em] text-muted uppercase lg:hidden">{{ __('app.admin.listings.columns.submitted') }}</span>
        {{ $submitted() }}
    </td>
    <td class="flex gap-3 py-1 lg:table-cell lg:px-3 lg:py-3 lg:align-middle">
        <span class="w-32 shrink-0 text-[11px] tracking-[0.12em] text-muted uppercase lg:hidden">{{ __('app.admin.listings.columns.status') }}</span>
        <span class="flex min-w-0 flex-col items-start gap-1">
            <x-listings.status-stamp :status="$listing->status" />
            @if ($listing->isRejected())
                <span class="text-xs text-red-deep wrap-anywhere">{{ $listing->rejection_reason }}</span>
            @endif
        </span>
    </td>
    <td class="flex flex-wrap items-center gap-2 pt-2 lg:table-cell lg:px-3 lg:py-3 lg:align-middle">
        @if ($showsActions())
            <div class="flex flex-wrap items-center gap-2">
                @can('approve', $listing)
                    <form method="POST" action="{{ $approveUrl() }}">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="version" value="{{ $listing->moderationVersion() }}">
                        <x-ui.button type="submit" variant="primary" size="responsive">
                            {{ __('app.listings.moderation.approve') }}<span class="sr-only"> {{ $listing->name }}</span>
                        </x-ui.button>
                    </form>
                @endcan
                @can('reject', $listing)
                    <x-listings.reject-dialog :action="$rejectUrl()" :version="$listing->moderationVersion()"
                                              :id="$rejectDialogId()" :field-id="$rejectDialogId().'-reason'" :context="$listing->name" />
                @endcan
                @if ($showsPromote())
                    @can('promoteModel', $listing)
                        <x-admin.promote-model-dialog :listing="$listing" :action="$promoteUrl()"
                                                      :id="$promoteDialogId()" :version="$listing->moderationVersion()" />
                    @endcan
                @endif
            </div>
        @endif
    </td>
</tr>
