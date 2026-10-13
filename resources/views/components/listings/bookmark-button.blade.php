<form method="POST" action="{{ $actionUrl() }}" {{ $attributes->merge(['class' => 'shrink-0']) }}>
    @csrf
    @if ($bookmarked())
        @method('DELETE')
    @endif
    <button type="submit" aria-pressed="{{ $bookmarked() ? 'true' : 'false' }}"
            class="inline-flex size-11 cursor-pointer items-center justify-center rounded-xs border text-red transition-colors duration-150 md:size-9 {{ $stateClasses() }}">
        <x-icons.bookmark :filled="$bookmarked()" class="size-4.5" />
        <span class="sr-only">{{ __('app.bookmarks.button.label', ['name' => $listing->name]) }}</span>
    </button>
</form>
