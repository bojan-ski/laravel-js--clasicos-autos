@props([
    'kicker' => null,
    'title',
])

<header {{ $attributes->merge(['class' => 'flex flex-col gap-3']) }}>
    @if ($kicker !== null)
        <x-ui.kicker>{{ $kicker }}</x-ui.kicker>
    @endif
    <h1 class="text-[40px] leading-none md:text-[58px]">{{ $title }}</h1>
</header>
