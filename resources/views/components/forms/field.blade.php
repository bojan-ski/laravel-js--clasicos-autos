@props([
    'name',
    'label',
    'type' => 'text',
    'placeholder' => null,
    'required' => false,
    'autocomplete' => null,
    'value' => null,
])

@php
    $errorId = $name.'-error';
    $hasError = $errors->has($name);
    $inputValue = $type === 'password' ? null : old($name, $value);
@endphp

<div class="flex flex-col gap-1.5">
    <label for="{{ $name }}" class="text-xs text-ink/70">{{ $label }}</label>
    <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}"
           @if ($inputValue !== null) value="{{ $inputValue }}" @endif
           @if ($placeholder !== null) placeholder="{{ $placeholder }}" @endif
           @if ($autocomplete !== null) autocomplete="{{ $autocomplete }}" @endif
           @if ($required) aria-required="true" @endif
           @if ($hasError) aria-invalid="true" aria-describedby="{{ $errorId }}" @endif
           {{ $attributes->class([
               'h-12 w-full rounded-xs border bg-card-input px-3 text-base text-ink placeholder:text-muted md:h-10.5 md:text-sm',
               'border-rule-strong' => ! $hasError,
               'border-red' => $hasError,
           ]) }}>
    @if ($hasError)
        <p id="{{ $errorId }}" class="text-xs text-red-deep">{{ $errors->first($name) }}</p>
    @endif
</div>
