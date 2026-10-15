<template data-live-bubble>
    <li class="{{ $classes }}">
        <p class="wrap-break-word"><span class="sr-only">{{ __('app.conversations.bubble.said', ['name' => $senderName]) }} </span><span data-live-bubble-text></span></p>
        <div class="{{ $metaClasses }}">
            <time data-live-bubble-time></time>
        </div>
    </li>
</template>
