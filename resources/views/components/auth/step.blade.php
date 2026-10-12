@props([
    'number',
    'title',
    'state' => 'active',
])

<div {{ $attributes->merge(['class' => 'flex flex-col gap-4.5']) }}>
    <h2 class="flex items-baseline gap-2 text-2xl">
        <span aria-hidden="true" @class(['text-red' => $state !== 'inactive', 'text-muted' => $state === 'inactive'])>{{ $number }}.</span>
        <span @class(['italic', 'text-ink' => $state !== 'inactive', 'text-muted' => $state === 'inactive'])>{{ $title }}</span>
    </h2>

    @if ($state !== 'inactive')
        {{ $slot }}
    @endif
</div>
