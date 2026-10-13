@props(['filled' => false])

<svg {{ $attributes->merge(['width' => 24, 'height' => 24]) }} xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="{{ $filled ? 'currentColor' : 'none' }}" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
    <path d="m19 21-7-4-7 4V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v16z" />
</svg>
