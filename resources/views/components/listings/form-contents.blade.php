@props(['sections'])

@php
    $numerals = ['I', 'II', 'III', 'IV', 'V', 'VI'];
@endphp

<nav aria-label="{{ __('app.listings.form.contents_label') }}" {{ $attributes }}>
    <ol class="flex flex-col gap-1 border-l border-rule">
        @foreach ($sections as $id => $title)
            <li>
                <a href="#{{ $id }}" class="flex gap-2 py-1.5 pl-4 text-sm text-ink hover:underline">
                    <span class="font-heading text-red">{{ $numerals[$loop->index] }}.</span>
                    <span>{{ $title }}</span>
                </a>
            </li>
        @endforeach
    </ol>
</nav>
