<template data-own-bubble data-own-bubble-placeholder="{{ $placeholder() }}">
    <li class="{{ $classes }}">
        <p id="{{ $textId() }}" class="wrap-break-word"><span class="sr-only">{{ __('app.conversations.bubble.said', ['name' => $senderName]) }} </span><span data-live-bubble-text></span></p>
        <div class="{{ $metaClasses }}">
            <time data-live-bubble-time></time>
            <x-conversations.delete-message-dialog :id="$deleteDialogId()" :action="$deleteAction()" />
        </div>
    </li>
</template>
