@props([
    'name',
    'label',
    'options' => [],
    'placeholder' => null,
    'value' => null,
    'hint' => null,
    'required' => false,
])

@php
    $errorId = $name.'-error';
    $hintId = $name.'-hint';
    $hasError = $errors->has($name);
    $oldValue = old($name, $value);
    $selectedValue = (string) (is_array($oldValue) ? $value : $oldValue);
    $describedBy = trim(($hint !== null ? $hintId : '').' '.($hasError ? $errorId : ''));
@endphp

<div class="flex flex-col gap-1.5">
    <label for="{{ $name }}" class="text-xs text-ink/70">{{ $label }}@if ($required) <span aria-hidden="true">*</span>@endif</label>
    <select id="{{ $name }}" name="{{ $name }}"
            @if ($required) required aria-required="true" @endif
            @if ($hasError) aria-invalid="true" aria-describedby="{{ $describedBy }}" @elseif ($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
            {{ $attributes->class([
                'h-12 w-full rounded-xs border bg-card-input px-3 text-base text-ink md:h-10.5 md:text-sm',
                'border-rule-input' => ! $hasError,
                'border-red' => $hasError,
            ]) }}>
        @if ($placeholder !== null)
            <option value="">{{ $placeholder }}</option>
        @endif
        @foreach ($options as $optionValue => $optionLabel)
            @if (is_array($optionLabel))
                <optgroup label="{{ $optionValue }}">
                    @foreach ($optionLabel as $groupValue => $groupLabel)
                        <option value="{{ $groupValue }}" @selected((string) $groupValue === $selectedValue)>{{ $groupLabel }}</option>
                    @endforeach
                </optgroup>
            @else
                <option value="{{ $optionValue }}" @selected((string) $optionValue === $selectedValue)>{{ $optionLabel }}</option>
            @endif
        @endforeach
    </select>
    @if ($hint !== null)
        <p id="{{ $hintId }}" class="text-xs text-muted">{{ $hint }}</p>
    @endif
    @if ($hasError)
        <p id="{{ $errorId }}" class="text-xs text-red-deep">{{ $errors->first($name) }}</p>
    @endif
</div>
