<x-layouts.app :title="$heading">
    <x-conversations.mobile-header :listing="$listing" :name="$counterpart" :back-url="$backUrl" />

    <div class="hidden md:block">
        <x-ui.back-link :href="$backUrl" />
    </div>

    <section class="mx-auto max-w-7xl px-5 py-6 md:px-14 md:py-12">
        <div class="md:grid md:grid-cols-[300px_1fr] md:gap-10">
            <div class="hidden md:block">
                <x-conversations.listing-postcard :listing="$listing" />
            </div>

            <div class="flex min-w-0 flex-col gap-6">
                <h1 class="border-b border-rule pb-4 text-[38px] leading-none wrap-break-word md:text-[56px]">{{ $heading }}</h1>

                @if ($markReadUrl !== null)
                    @if ($messages->isEmpty())
                        <p data-live-empty class="text-muted italic">{{ $emptyMessage }}</p>
                    @endif
                    <div role="log" aria-label="{{ __('app.conversations.messages_label') }}" data-live-thread="{{ $conversation->id }}" data-mark-read-url="{{ $markReadUrl }}">
                        <ol role="list" class="flex flex-col gap-4"{{ $messages->isEmpty() ? ' hidden' : '' }}>
                            @foreach ($messages as $message)
                                <x-conversations.message-bubble :message="$message" :conversation="$conversation" />
                            @endforeach
                        </ol>
                        <x-conversations.live-bubble :conversation="$conversation" />
                    </div>
                @elseif ($messages->isEmpty())
                    <p class="text-muted italic">{{ $emptyMessage }}</p>
                @else
                    <ol role="list" class="flex flex-col gap-4" aria-label="{{ __('app.conversations.messages_label') }}">
                        @foreach ($messages as $message)
                            <x-conversations.message-bubble :message="$message" :conversation="$conversation" />
                        @endforeach
                    </ol>
                @endif

                @if ($composerAction !== null)
                    <x-conversations.composer :action="$composerAction" />
                @endif
            </div>
        </div>
    </section>
</x-layouts.app>
