<article @class([
    'relative grid grid-cols-[96px_1fr] gap-3.5 border border-rule p-3 has-[a:focus-visible]:outline-2 has-[a:focus-visible]:outline-offset-2 has-[a:focus-visible]:outline-gold-ink',
    'bg-card-unread shadow-md border-l-3 border-l-red' => $isUnread(),
    'bg-card shadow-sm' => ! $isUnread(),
])>
    @can('view', $conversation->listing)
        @if ($thumbUrl() !== null)
            <img src="{{ $thumbUrl() }}" alt="" width="320" height="240" loading="lazy" class="aspect-4/3 w-24 self-start object-cover photo-grade">
        @else
            <div class="flex aspect-4/3 w-24 items-center justify-center self-start border border-rule bg-paper text-muted">
                <x-icons.image class="size-8" />
            </div>
        @endif
    @else
        <div class="flex aspect-4/3 w-24 items-center justify-center self-start border border-rule bg-paper text-muted">
            <x-icons.image class="size-8" />
        </div>
    @endcan

    <div class="flex min-w-0 flex-col gap-1">
        <div class="flex items-start justify-between gap-3">
            <h2 class="min-w-0 font-heading text-[22px] leading-tight wrap-break-word">
                <a href="{{ $url() }}"@if ($isUnread()) aria-describedby="{{ $unreadId() }}"@endif class="after:absolute after:inset-0 focus-visible:outline-none">{{ $heading }}<span class="sr-only"> {{ __('app.conversations.index.link_context', ['listing' => $conversation->listing->name]) }}</span></a>
            </h2>
            @if ($timeIso() !== null)
                <time datetime="{{ $timeIso() }}" class="shrink-0 text-[11px] text-muted">{{ $timeHuman() }}</time>
            @endif
        </div>

        <p class="text-sm text-red italic wrap-break-word">{{ __('app.conversations.index.regarding') }} {{ $conversation->listing->name }}</p>
        @cannot('view', $conversation->listing)
            <p class="text-xs text-muted">{{ __('app.conversations.listing.unavailable') }}</p>
        @endcannot

        @if ($lastMessage() !== null)
            <p class="mt-1 truncate border-t border-rule pt-2 text-[13px] text-ink-2">{{ $lastMessage() }}</p>
        @else
            <p class="mt-1 truncate border-t border-rule pt-2 text-[13px] text-muted italic">{{ __('app.conversations.index.no_messages') }}</p>
        @endif
    </div>

    @if ($isUnread())
        <x-ui.stamp tone="red" class="pointer-events-none absolute -top-2.5 -right-2.5 rotate-4 bg-card">
            <span class="block px-2 py-0.5 text-[10px] tracking-[0.16em]"><span class="uppercase">{{ __('app.conversations.index.stamp') }}</span><span id="{{ $unreadId() }}" class="sr-only"> {{ trans_choice('app.conversations.index.stamp_count', $unreadCount()) }}</span></span>
        </x-ui.stamp>
    @endif
</article>
