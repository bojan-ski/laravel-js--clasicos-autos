@php
    $messages = array_filter([
        'success' => session('success'),
        'error' => session('error'),
    ]);
@endphp

@if ($messages !== [])
    <x-global.flash-overlay :sticky="isset($messages['error'])">
        @foreach ($messages as $type => $message)
            <x-global.flash-stamp :type="$type" :message="$message" />
        @endforeach
    </x-global.flash-overlay>
@endif

@stack('flash-templates')
