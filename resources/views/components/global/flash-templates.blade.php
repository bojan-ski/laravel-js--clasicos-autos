@foreach (['success', 'error'] as $type)
    <template data-flash-template="{{ $type }}">
        <x-global.flash-overlay :sticky="$type === 'error'">
            <x-global.flash-stamp :type="$type" />
        </x-global.flash-overlay>
    </template>
@endforeach
