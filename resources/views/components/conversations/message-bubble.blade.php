<li id="message-{{ $message->id }}" {{ $attributes->class([
    \App\View\Components\Conversations\MessageBubble::BASE_CLASSES,
    'ml-auto bg-card-unread border-red rotate-[0.4deg]' => $own,
    \App\View\Components\Conversations\MessageBubble::OTHER_CLASSES => ! $own,
]) }}>
    <p class="wrap-break-word">@unless ($showsSender)<span class="sr-only">{{ __('app.conversations.bubble.said', ['name' => $senderName]) }} </span>@endunless{{ $message->message }}</p>
    <div class="{{ \App\View\Components\Conversations\MessageBubble::META_CLASSES }}">
        @if ($showsSender)
            <span class="wrap-break-word">{{ $senderName }}</span>
        @endif
        <time datetime="{{ $datetime() }}">{{ $time() }}</time>
        @can('delete', $message)
            <x-conversations.delete-message-dialog :message="$message" :action="$deleteAction()" :time="$time()" />
        @endcan
    </div>
</li>
