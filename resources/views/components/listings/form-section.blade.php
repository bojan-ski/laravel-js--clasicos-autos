@props([
    'id',
    'number',
    'title',
    'note' => null,
])

<fieldset id="{{ $id }}" data-form-section {{ $attributes->merge(['class' => 'scroll-mt-6 border-t border-rule py-8 first:border-t-0 first:pt-0']) }}>
    <legend class="float-left w-full">
        <h2 class="font-heading text-[26px] leading-tight"><span class="text-red">{{ $number }}.</span> <span class="italic">{{ $title }}</span></h2>
    </legend>
    @if ($note !== null)
        <div class="clear-left mt-1 text-xs text-muted">{{ $note }}</div>
    @endif
    <div class="clear-left mt-5 grid grid-cols-1 gap-4 lg:grid-cols-2">
        {{ $slot }}
    </div>
</fieldset>
