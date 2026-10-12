<x-layouts.admin :title="__('app.admin.account.title')">
    <div class="mx-auto flex max-w-7xl flex-col gap-10 px-5 py-6 md:px-14 md:py-16">
        <x-ui.page-header :kicker="__('app.admin.account.kicker')" kicker-lang="es" :title="__('app.admin.account.title')" />
        <x-account.password-form :action="route('admin.account.password.update')" class="max-w-130" />
    </div>
</x-layouts.admin>
