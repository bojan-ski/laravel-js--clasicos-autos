@props([
    'name',
    'value' => '1',
    'checked' => false,
    'required' => false,
])

@php
    $errorId = $name.'-error';
    $hasError = $errors->has($name);
    $isChecked = old($name) !== null ? (string) old($name) === (string) $value : $checked;
@endphp

<div class="flex flex-col gap-1">
    <label for="{{ $name }}" class="flex min-h-11 items-center gap-2 text-sm md:min-h-6">
        <input id="{{ $name }}" name="{{ $name }}" type="checkbox" value="{{ $value }}"
               @checked($isChecked)
               @if ($required) aria-required="true" @endif
               @if ($hasError) aria-invalid="true" aria-describedby="{{ $errorId }}" @endif
               {{ $attributes->class(['size-4 shrink-0 accent-red']) }}>
        <span>{{ $slot }}</span>
    </label>
    @if ($hasError)
        <p id="{{ $errorId }}" class="text-xs text-red-deep">{{ $errors->first($name) }}</p>
    @endif
</div>
