<x-layouts.error :code="403" :title="__('app.errors.403.title')">
    <x-ui.error-page code="403"
                     :stamp="__('app.errors.403.stamp')"
                     :title="__('app.errors.403.title')"
                     :message="__('app.errors.403.message')">
        <x-ui.button :href="route('home')" variant="secondary" size="lg">{{ __('app.errors.home') }}</x-ui.button>
    </x-ui.error-page>
</x-layouts.error>
