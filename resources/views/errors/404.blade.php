<x-layouts.error :code="404" :title="__('app.errors.404.title')">
    <x-ui.error-page code="404"
                     :stamp="__('app.errors.404.stamp')"
                     :title="__('app.errors.404.title')"
                     :message="__('app.errors.404.message')">
        <x-ui.button :href="route('home')" variant="secondary" size="lg">{{ __('app.errors.home') }}</x-ui.button>
    </x-ui.error-page>
</x-layouts.error>
