@props(['listings'])

<section aria-labelledby="latest-listings-title" {{ $attributes->merge(['class' => 'mx-auto flex max-w-7xl flex-col gap-6 px-5 pb-7 md:px-14 md:pb-14']) }}>
    <div class="flex items-baseline justify-between gap-4">
        <h2 id="latest-listings-title" class="text-[30px] leading-tight md:text-[40px]">{{ __('app.home.latest.title') }}</h2>
        <a href="{{ route('listings.index') }}" class="shrink-0 text-red underline-offset-4 hover:underline">{{ __('app.home.latest.all') }} <span aria-hidden="true">→</span></a>
    </div>

    <div class="grid gap-9 md:grid-cols-2 lg:grid-cols-3">
        @foreach ($listings as $listing)
            <x-listings.card :listing="$listing" :heading-level="3" />
        @endforeach
    </div>
</section>
