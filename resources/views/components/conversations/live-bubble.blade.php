<template data-live-bubble data-live-bubble-placeholder="{{ \App\View\Components\Conversations\LiveBubble::PLACEHOLDER }}">
    <li class="{{ $classes }}">
        <p id="{{ $textId() }}" class="wrap-break-word"><span class="sr-only">{{ __('app.conversations.bubble.said', ['name' => $senderName]) }} </span><span data-live-bubble-text></span></p>
        <div class="{{ $metaClasses }}">
            <time data-live-bubble-time></time>
            @can('reply', $conversation)
                <x-conversations.report-message-dialog :id="$reportDialogId()" :action="$reportAction()" :describedby="$textId()" />
            @endcan
        </div>
    </li>
</template>
