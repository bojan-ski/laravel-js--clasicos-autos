@props(['section'])

<section id="{{ $section->anchor }}" {{ $attributes->merge(['class' => 'flex flex-col gap-2 border-t border-rule pt-4.5']) }}>
    <h2 class="text-[22px] text-red md:text-[28px]">{{ $section->heading }}</h2>

    @if ($section->items !== null)
        <ul class="list-disc pl-5 text-sm leading-[1.6] md:text-[15px]">
            @foreach ($section->items as $item)
                <li>{{ $item }}</li>
            @endforeach
        </ul>
    @else
        <p class="text-justify text-sm leading-[1.7] hyphens-auto md:text-[15px]">{{ $section->paragraph }}</p>
    @endif
</section>
