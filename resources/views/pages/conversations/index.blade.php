<x-layouts.app :title="$heading">
    <section class="mx-auto flex max-w-7xl flex-col gap-6 px-5 py-6 md:gap-8 md:px-14 md:py-12"@if ($liveList) data-live-list=""{{ $firstPage ? ' data-first-page' : '' }}@endif>
        <div>
            <div class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
                <x-ui.page-header :kicker="__('app.conversations.index.kicker')" kicker-lang="es" :title="$heading" />
                @if ($showsUnreadLine)
                    <p role="status"@if ($liveList) data-live-unread data-template="{{ __('app.conversations.index.unread') }}"@endif class="text-sm text-ink-2 tabular-nums">{{ $unreadLine }}</p>
                @endif
            </div>

            @if ($liveList)
                <div role="status" data-live-notice-region></div>
                <template data-live-notice>
                    <p class="mt-4 border-l-3 border-l-red bg-card-unread px-4 py-3 text-sm text-ink md:mt-6">{{ __('app.conversations.index.notice') }} <a href="{{ route('conversations.index') }}" class="text-red underline">{{ __('app.conversations.index.notice_link') }}</a></p>
                </template>
            @endif
        </div>

        @if ($conversations->total() === 0)
            <x-listings.empty-state :message="$emptyMessage" :action-href="$browseUrl" :action-label="__('app.conversations.index.browse')" />
        @else
            @if ($conversations->isNotEmpty())
                <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                    @foreach ($conversations as $conversation)
                        <x-conversations.card :conversation="$conversation" />
                    @endforeach
                </div>
            @endif

            {{ $conversations->links() }}
        @endif
    </section>
</x-layouts.app>
