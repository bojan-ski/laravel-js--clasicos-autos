<section class="mx-auto max-w-7xl px-5 py-6 md:p-14">
    <x-ui.postcard tilt="left" class="grid gap-3.5 pb-4.5 md:grid-cols-[7fr_5fr] md:gap-0 md:p-4.5">
        <div class="relative">
            <img src="{{ asset('assets/images/hero.jpg') }}" alt="{{ __('app.home.hero.image_alt') }}"
                 width="1184" height="672"
                 class="h-60 w-full object-cover photo-grade md:h-125">
            <span lang="es" class="absolute bottom-3 left-3.5 font-heading text-[30px] leading-none text-cream-on-dark italic [text-shadow:0_2px_10px_rgba(0,0,0,.45)] md:bottom-5.5 md:left-7 md:text-[54px]">{{ __('app.home.hero.overlay') }}</span>

            <x-ui.stamp tone="red" class="absolute top-2.5 right-2.5 h-16 w-13.5 bg-card md:hidden">
                <span class="grid size-full place-items-center font-heading text-xl">{{ __('app.home.hero.stamp_year') }}</span>
            </x-ui.stamp>
        </div>

        <div class="relative flex flex-col gap-3 px-1.5 md:ml-7 md:gap-4.5 md:border-l md:border-rule md:py-7 md:pr-2 md:pl-9">
            <div class="absolute top-2 right-0 hidden h-29 w-24 md:block">
                <x-ui.stamp tone="red" lang="es" class="size-full">
                    <span class="flex size-full flex-col items-center justify-center">
                        <span class="text-[10px] uppercase tracking-[0.16em]">{{ __('app.home.hero.stamp_country') }}</span>
                        <span class="font-heading text-[34px] leading-none">{{ __('app.home.hero.stamp_year') }}</span>
                        <span class="text-[10px] uppercase tracking-[0.16em]">{{ __('app.home.hero.stamp_post') }}</span>
                    </span>
                </x-ui.stamp>
            </div>

            <x-ui.kicker lang="es" class="hidden md:block">{{ __('app.home.hero.kicker') }}</x-ui.kicker>
            <h1 class="text-[38px] leading-[1.02] md:mt-10 md:mr-27.5 md:text-[54px] md:leading-none">{{ __('app.home.hero.title') }}</h1>
            <p class="text-[15px] text-ink-2 md:mt-auto md:border-b md:border-rule-strong md:pb-2.5 md:text-[17px] md:text-ink">{{ __('app.home.hero.line') }}</p>
            <x-ui.button :href="route('listings.index')" variant="primary" size="hero" class="w-full md:w-auto md:self-start">{{ __('app.home.hero.cta') }}</x-ui.button>
        </div>
    </x-ui.postcard>
</section>
