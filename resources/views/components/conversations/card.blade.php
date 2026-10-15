<article class="group relative grid grid-cols-[96px_1fr] gap-3.5 border border-rule bg-card p-3 shadow-sm has-[a:focus-visible]:outline-2 has-[a:focus-visible]:outline-offset-2 has-[a:focus-visible]:outline-gold-ink data-unread:border-l-3 data-unread:border-l-red data-unread:bg-card-unread data-unread:shadow-md"@if ($isParticipant) data-conversation-card="{{ $conversation->id }}" data-unread-count="{{ $unreadCount() }}" data-last-message-id="{{ $lastMessageId() }}"{{ $isUnread() ? ' data-unread' : '' }}@endif>
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
        <div class="flex items-start justify-between gap-3 group-data-unread:pr-12">
            <h2 class="min-w-0 font-heading text-[22px] leading-tight wrap-break-word">
                <a href="{{ $url() }}"@if ($isUnread()) aria-describedby="{{ $unreadId() }}"@endif class="after:absolute after:inset-0 focus-visible:outline-none">{{ $heading }}<span class="sr-only"> {{ __('app.conversations.index.link_context', ['listing' => $conversation->listing->name]) }}</span></a>
            </h2>
            @if ($timeIso() !== null)
                <time datetime="{{ $timeIso() }}"@if ($isParticipant) data-card-time=""@endif class="shrink-0 text-[11px] text-muted">{{ $timeHuman() }}</time>
            @elseif ($isParticipant)
                <time data-card-time hidden class="shrink-0 text-[11px] text-muted"></time>
            @endif
        </div>

        <p class="text-sm text-red italic wrap-break-word">{{ __('app.conversations.index.regarding') }} {{ $conversation->listing->name }}</p>
        @cannot('view', $conversation->listing)
            <p class="text-xs text-muted">{{ __('app.conversations.listing.unavailable') }}</p>
        @endcannot

        <p class="mt-1 truncate border-t border-rule pt-2 text-[13px] text-ink-2 data-empty:text-muted data-empty:italic"@if ($isParticipant) data-card-message=""@endif{{ $lastMessage() === null ? ' data-empty' : '' }}>{{ $lastMessage() ?? __('app.conversations.index.no_messages') }}</p>
    </div>

    @if ($isParticipant)
        <div class="pointer-events-none absolute -top-2.5 -right-2.5 hidden rotate-4 group-data-unread:block">
            <x-ui.stamp tone="red" class="bg-card">
                <span class="block px-2 py-0.5 text-[10px] tracking-[0.16em]"><span class="uppercase">{{ __('app.conversations.index.stamp') }}</span><span id="{{ $unreadId() }}" data-card-count data-template-one="{{ $countTemplate(1) }}" data-template-other="{{ $countTemplate(2) }}" class="sr-only"> {{ trans_choice('app.conversations.index.stamp_count', $unreadCount()) }}</span></span>
            </x-ui.stamp>
        </div>
    @endif
</article>
