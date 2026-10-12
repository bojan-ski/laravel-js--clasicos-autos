@props(['document'])

<div {{ $attributes->merge(['class' => 'mx-auto grid max-w-7xl gap-4.5 px-5 pt-6 pb-8 md:grid-cols-[4fr_8fr] md:items-start md:gap-14 md:px-14 md:pb-16']) }}>
    <div class="flex flex-col gap-5">
        <x-ui.page-header :kicker="$document->kicker" kicker-class="max-md:hidden" :title="$document->title" />

        <x-ui.stamp tone="red" class="self-start md:-rotate-1">
            <span class="flex flex-col px-4 py-3 text-xs text-red-deep md:text-[13px]">
                @foreach ($document->disclaimer as $line)
                    <span>{{ $line }}</span>
                @endforeach
            </span>
        </x-ui.stamp>

        <x-legal.contents :sections="$document->sections" />
    </div>

    <article class="flex flex-col gap-5.5 bg-card p-5 md:px-13 md:py-11 md:shadow-md">
        <p class="font-heading text-xl leading-[1.45] italic md:text-2xl">{{ $document->intro }}</p>

        @foreach ($document->sections as $section)
            <x-legal.section :section="$section" />
        @endforeach
    </article>
</div>
