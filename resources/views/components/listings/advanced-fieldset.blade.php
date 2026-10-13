@props(['title', 'note'])

<fieldset {{ $attributes->merge(['class' => 'grid gap-y-1 border-t border-rule py-6 first:border-t-0 first:pt-0 lg:grid-cols-[200px_1fr] lg:grid-rows-[auto_1fr] lg:gap-x-8']) }}>
    <legend class="float-left font-heading text-[24px] leading-tight text-red italic lg:col-start-1 lg:row-start-1">{{ $title }}</legend>
    <p class="text-xs text-muted lg:col-start-1 lg:row-start-2">{{ $note }}</p>
    <div class="mt-3 grid grid-cols-2 gap-4 md:grid-cols-4 lg:col-start-2 lg:row-span-2 lg:row-start-1 lg:mt-0">
        {{ $slot }}
    </div>
</fieldset>
