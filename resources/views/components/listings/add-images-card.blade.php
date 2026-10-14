@props([
    'action',
    'canAdd',
])

<div {{ $attributes->merge(['class' => 'flex flex-col bg-card p-7 shadow-sm']) }}>
    @if ($canAdd)
        <form method="POST" action="{{ $action }}" enctype="multipart/form-data" class="flex flex-1 flex-col gap-5">
            @csrf

            <x-listings.photo-input class="md:flex-1" :label="__('app.listings.images.add_label')"
                                    label-class="font-heading text-[26px] leading-tight text-red italic" />

            <x-ui.button type="submit" size="responsive" class="w-full md:w-auto md:self-start">
                {{ __('app.listings.images.add_submit') }}
            </x-ui.button>
        </form>
    @else
        <h2 class="text-[26px] leading-tight text-red italic">{{ __('app.listings.images.add_label') }}</h2>
        <p class="mt-4 flex items-start gap-3 text-ink">
            <x-icons.circle-alert class="mt-0.5 size-5 shrink-0 text-gold-ink" />
            <span>{{ __('app.listings.images.limit_reached') }}</span>
        </p>
    @endif
</div>
