@props(['listing'])

<section aria-label="{{ __('app.listings.show.status.label') }}"
         {{ $attributes->merge(['class' => 'flex flex-wrap items-center gap-x-4 gap-y-2 border border-rule bg-card px-4 py-3']) }}>
    <x-listings.status-stamp :status="$listing->status" />
    @if ($listing->isRejected() && $listing->rejection_reason !== null)
        <p class="text-sm text-red-deep wrap-break-word">{{ __('app.listings.show.status.reason') }} {{ $listing->rejection_reason }}</p>
    @endif
    @if ($listing->owner->isSuspended())
        <p class="text-sm text-muted">{{ __('app.listings.show.status.owner_suspended') }}</p>
    @endif
    @canany(['approve', 'reject'], $listing)
        @if ($listing->canBeApproved() || $listing->canBeRejected())
            <div class="ml-auto flex w-full flex-wrap items-center gap-2 md:w-auto">
                @can('approve', $listing)
                    <form method="POST" action="{{ route('admin.listings.approve', $listing) }}" class="w-full md:w-auto">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="version" value="{{ $listing->moderationVersion() }}">
                        <x-ui.button type="submit" variant="primary" size="responsive" class="w-full md:w-auto">
                            {{ __('app.listings.moderation.approve') }}
                        </x-ui.button>
                    </form>
                @endcan
                @can('reject', $listing)
                    <x-listings.reject-dialog :action="route('admin.listings.reject', $listing)" :version="$listing->moderationVersion()"
                                              class="w-full md:w-auto" />
                @endcan
            </div>
        @endif
    @endcanany
</section>
