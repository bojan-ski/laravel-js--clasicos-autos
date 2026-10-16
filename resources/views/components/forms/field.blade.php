@props([
    'name',
    'label',
    'type' => 'text',
    'placeholder' => null,
    'hint' => null,
    'required' => false,
    'autocomplete' => null,
    'value' => null,
    'id' => null,
    'bag' => null,
])

@php
    $fieldId = $id ?? $name;
    $errorId = $fieldId.'-error';
    $hintId = $fieldId.'-hint';
    $fieldErrors = $bag === null ? $errors : $errors->getBag($bag);
    $hasError = $fieldErrors->has($name);
    $oldValue = $bag === null || $fieldErrors->any() ? old($name, $value) : $value;
    $inputValue = $type === 'password' ? null : (is_array($oldValue) ? $value : $oldValue);
    $describedBy = trim(($hint !== null ? $hintId : '').' '.($hasError ? $errorId : ''));
@endphp

<div class="flex flex-col gap-1.5">
    <label for="{{ $fieldId }}" class="text-xs text-ink/70">{{ $label }}@if ($required) <span aria-hidden="true">*</span>@endif</label>
    <input id="{{ $fieldId }}" name="{{ $name }}" type="{{ $type }}"
           @if ($inputValue !== null) value="{{ $inputValue }}" @endif
           @if ($placeholder !== null) placeholder="{{ $placeholder }}" @endif
           @if ($autocomplete !== null) autocomplete="{{ $autocomplete }}" @endif
           @if ($required) required aria-required="true" @endif
           @if ($hasError) aria-invalid="true" aria-describedby="{{ $describedBy }}" @elseif ($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
           {{ $attributes->class([
               'h-12 w-full rounded-xs border bg-card-input px-3 text-base text-ink placeholder:text-muted md:h-10.5 md:text-sm',
               'border-rule-input' => ! $hasError,
               'border-red' => $hasError,
           ]) }}>
    @if ($hint !== null)
        <p id="{{ $hintId }}" class="text-xs text-muted">{{ $hint }}</p>
    @endif
    @if ($hasError)
        <p id="{{ $errorId }}" class="text-xs text-red-deep">{{ $fieldErrors->first($name) }}</p>
    @endif
</div>
