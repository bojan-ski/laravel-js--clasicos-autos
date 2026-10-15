@props([
    'name',
    'label',
    'id' => null,
    'placeholder' => null,
    'hint' => null,
    'required' => false,
    'value' => null,
])

@php
    $fieldId = $id ?? $name;
    $errorId = $fieldId.'-error';
    $hintId = $fieldId.'-hint';
    $hasError = $errors->has($name);
    $oldValue = old($name, $value);
    $textValue = is_array($oldValue) ? $value : $oldValue;
    $describedBy = trim(($hint !== null ? $hintId : '').' '.($hasError ? $errorId : ''));
@endphp

<div class="flex flex-col gap-1.5">
    <label for="{{ $fieldId }}" class="text-xs text-ink/70">{{ $label }}@if ($required) <span aria-hidden="true">*</span>@endif</label>
    <textarea id="{{ $fieldId }}" name="{{ $name }}"
              @if ($placeholder !== null) placeholder="{{ $placeholder }}" @endif
              @if ($required) required aria-required="true" @endif
              @if ($hasError) aria-invalid="true" aria-describedby="{{ $describedBy }}" @elseif ($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
              {{ $attributes->class([
                  'h-50 w-full rounded-xs border bg-card-input px-3 py-2.5 text-base text-ink placeholder:text-muted md:text-sm',
                  'border-rule-input' => ! $hasError,
                  'border-red' => $hasError,
              ]) }}>{{ $textValue }}</textarea>
    @if ($hint !== null)
        <p id="{{ $hintId }}" class="text-xs text-muted">{{ $hint }}</p>
    @endif
    @if ($hasError)
        <p id="{{ $errorId }}" class="text-xs text-red-deep">{{ $errors->first($name) }}</p>
    @endif
</div>
