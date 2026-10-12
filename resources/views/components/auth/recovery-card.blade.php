@props(['title'])

<div {{ $attributes->merge(['class' => 'relative mx-auto flex w-full max-w-160 flex-col gap-5.5 bg-card p-6 shadow-md md:p-10']) }}>
    <x-ui.stamp tone="red" aria-hidden="true" class="absolute top-4 right-4 h-14.5 w-12 md:top-6 md:right-6 md:h-19.5 md:w-16">
        <span class="grid size-full place-items-center font-heading text-lg md:text-[22px]">{{ __('app.auth.forgot.stamp') }}</span>
    </x-ui.stamp>

    <div class="flex flex-col gap-1.5">
        <x-ui.kicker lang="es">{{ __('app.auth.forgot.kicker') }}</x-ui.kicker>
        <h1 class="text-[38px] leading-none md:text-[52px]">{{ $title }}</h1>
    </div>

    {{ $slot }}
</div>
