@props(['title', 'rows'])

<div {{ $attributes }}>
    <details class="group border-b border-rule md:hidden motion-public:details-smooth">
        <summary class="flex min-h-12 cursor-pointer list-none items-center justify-between gap-4 py-2 [&::-webkit-details-marker]:hidden">
            <span class="font-heading text-2xl text-red italic">{{ $title }}</span>
            <span aria-hidden="true" class="font-heading text-3xl leading-none text-red transition-transform group-open:rotate-45">+</span>
        </summary>
        <x-ui.spec-list :rows="$rows" class="pb-4" />
    </details>

    <section class="hidden h-full rounded-xs border border-rule bg-card p-6 shadow-sm md:block">
        <h2 class="mb-3 text-2xl text-red italic">{{ $title }}</h2>
        <x-ui.spec-list :rows="$rows" />
    </section>
</div>
