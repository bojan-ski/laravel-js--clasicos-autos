@pushOnce('flash-templates', 'flash-templates')
    <x-global.flash-templates />
@endPushOnce

<form method="POST" action="{{ $actionUrl() }}" data-toggle-form="bookmark" {{ $attributes->merge(['class' => 'shrink-0']) }}>
    @csrf
    @if ($bookmarked())
        @method('DELETE')
    @endif
    <button type="submit" aria-pressed="{{ $bookmarked() ? 'true' : 'false' }}"
            class="group inline-flex size-11 cursor-pointer items-center justify-center rounded-xs border border-rule text-red transition-colors duration-150 hover:bg-ink/7 aria-pressed:border-red aria-pressed:bg-red/10 aria-pressed:hover:bg-red/10 md:size-9">
        <x-icons.bookmark :filled="$bookmarked()" class="size-4.5 fill-none group-aria-pressed:fill-current motion-public:group-data-toggled:group-aria-pressed:animate-press" />
        <span class="sr-only">{{ __('app.bookmarks.button.label', ['name' => $listing->name]) }}</span>
    </button>
</form>
