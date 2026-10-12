@props([
    'kicker' => null,
    'kickerClass' => '',
    'kickerLang' => null,
    'title',
])

<header {{ $attributes->merge(['class' => 'flex flex-col gap-3']) }}>
    @if ($kicker !== null)
        <x-ui.kicker :class="$kickerClass" :lang="$kickerLang">{{ $kicker }}</x-ui.kicker>
    @endif
    <h1 class="text-[38px] leading-none md:text-[56px]">{{ $title }}</h1>
</header>
