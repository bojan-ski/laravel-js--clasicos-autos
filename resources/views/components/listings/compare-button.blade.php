<form method="POST" action="{{ $actionUrl() }}" {{ $attributes->merge(['class' => 'shrink-0']) }}>
    @csrf
    @if ($selected())
        @method('DELETE')
    @endif
    <button type="submit" aria-pressed="{{ $selected() ? 'true' : 'false' }}"
            class="relative inline-flex size-11 cursor-pointer items-center justify-center rounded-xs border transition-colors duration-150 md:size-9 {{ $stateClasses() }}">
        <x-icons.arrow-left-right class="size-4.5" />
        <span class="sr-only">{{ __('app.compare.button.label', ['name' => $listing->name]) }}</span>
        @if ($selected())
            <span class="absolute -top-1.5 -right-1.5 flex size-3.5 items-center justify-center rounded-full bg-red text-card">
                <x-icons.check class="size-2.5" />
            </span>
        @endif
    </button>
</form>
