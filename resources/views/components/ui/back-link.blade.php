@props([
    'href',
    'label' => null,
    'slotClass' => '',
])

<div {{ $attributes->merge(['class' => 'border-b border-rule md:border-b-0']) }}>
    <div @class([
        'mx-auto flex max-w-7xl items-center gap-1 px-2 py-1 md:px-14 md:pt-7 md:pb-0',
        'flex-wrap' => $slot->isNotEmpty(),
    ])>
        <a href="{{ $href }}" class="inline-flex size-11 items-center justify-center gap-2 text-sm text-ink-2 md:size-auto md:justify-start">
            <x-icons.arrow-left class="size-5 md:size-4" />
            <span class="max-md:sr-only">{{ __('app.back_link') }}</span>
        </a>
        @if ($label !== null)
            <span class="font-heading text-[22px] md:hidden">{{ $label }}</span>
        @endif
        @if ($slot->isNotEmpty())
            <div @class(['ml-auto flex flex-wrap items-center justify-end gap-2', $slotClass => $slotClass !== ''])>
                {{ $slot }}
            </div>
        @endif
    </div>
</div>
