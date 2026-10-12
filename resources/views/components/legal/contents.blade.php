@props(['sections'])

<nav aria-label="{{ __('app.legal.contents') }}" {{ $attributes->merge(['class' => 'hidden border-t border-ink md:block']) }}>
    <ul>
        @foreach ($sections as $section)
            <li class="border-b border-rule">
                <a href="#{{ $section->anchor }}" class="block py-2 text-sm text-ink">{{ $section->heading }}</a>
            </li>
        @endforeach
    </ul>
</nav>
