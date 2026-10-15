<li id="message-{{ $message->id }}" {{ $attributes->class([
    'w-fit max-w-110 scroll-mt-16 border px-4 py-3',
    'ml-auto bg-card-unread border-red rotate-[0.4deg]' => $own,
    'bg-card border-rule -rotate-[0.4deg]' => ! $own,
]) }}>
    <p class="wrap-break-word">@unless ($showsSender)<span class="sr-only">{{ __('app.conversations.bubble.said', ['name' => $senderName]) }} </span>@endunless{{ $message->message }}</p>
    <div class="mt-1 flex flex-wrap items-center gap-x-3 text-[11px] text-muted">
        @if ($showsSender)
            <span class="wrap-break-word">{{ $senderName }}</span>
        @endif
        <time datetime="{{ $datetime() }}">{{ $time() }}</time>
        @can('delete', $message)
            <x-conversations.delete-message-dialog :message="$message" :action="$deleteAction()" :time="$time()" />
        @endcan
    </div>
</li>
