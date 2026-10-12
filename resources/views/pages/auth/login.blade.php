<x-layouts.guest :title="__('app.auth.login.title')" width="wide">
    <div class="grid items-center gap-14 md:grid-cols-[6fr_5fr]">
        <x-ui.postcard tilt="right" class="relative hidden md:block md:p-4 md:pb-5.5">
            <img src="{{ asset('assets/images/hero.jpg') }}" alt="" width="1184" height="672"
                 class="h-110 w-full object-cover photo-grade">
            <span lang="es" aria-hidden="true" class="absolute bottom-13 left-10 font-heading text-[46px] leading-none text-card italic [text-shadow:0_2px_10px_rgba(0,0,0,.45)]">{{ __('app.auth.login.overlay') }}</span>
        </x-ui.postcard>

        <x-auth.login-form />
    </div>
</x-layouts.guest>
