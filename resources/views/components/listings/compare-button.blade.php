@pushOnce('flash-templates', 'flash-templates')
    <x-global.flash-templates />
@endPushOnce

<form method="POST" action="{{ $actionUrl() }}" data-toggle-form="compare" {{ $attributes->merge(['class' => 'shrink-0']) }}>
    @csrf
    @if ($selected())
        @method('DELETE')
    @endif
    <button type="submit" aria-pressed="{{ $selected() ? 'true' : 'false' }}"
            class="group relative inline-flex size-11 cursor-pointer items-center justify-center rounded-xs border border-rule text-ink-2 transition-colors duration-150 hover:bg-ink/7 aria-pressed:border-red aria-pressed:bg-red/10 aria-pressed:text-red aria-pressed:hover:bg-red/10 md:size-9">
        <x-icons.arrow-left-right class="size-4.5" />
        <span class="sr-only">{{ __('app.compare.button.label', ['name' => $listing->name]) }}</span>
        <span class="absolute -top-1.5 -right-1.5 hidden size-3.5 items-center justify-center rounded-full bg-red text-card group-aria-pressed:flex">
            <x-icons.check class="size-2.5" />
        </span>
    </button>
</form>
