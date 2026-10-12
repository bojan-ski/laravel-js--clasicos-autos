<div data-loading-overlay hidden role="status"
     class="fixed inset-0 z-70 flex flex-col items-center justify-center gap-6 bg-paper/94 px-5 text-center">
    <div class="relative grid size-40 place-items-center">
        <span class="absolute inset-0 rounded-full border-2 border-dashed border-red motion-safe:animate-spin-slow"></span>
        <x-ui.stamp tone="gold" class="bg-card">
            <span class="grid size-16 place-items-center text-red">
                <x-icons.car class="size-9" />
            </span>
        </x-ui.stamp>
    </div>
    <p class="font-heading text-[32px] text-red-deep italic">{{ __('app.loading.caption') }}</p>
</div>
