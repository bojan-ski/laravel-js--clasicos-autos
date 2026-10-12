@props([
    'prompt',
    'label',
    'href',
])

<p {{ $attributes->merge(['class' => 'text-sm']) }}>
    {{ $prompt }} <a href="{{ $href }}" class="text-red underline underline-offset-2">{{ $label }}</a>
</p>
