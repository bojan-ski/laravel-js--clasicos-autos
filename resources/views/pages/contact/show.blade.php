<x-layouts.app :title="__('app.contact.title')">
    <div class="mx-auto flex max-w-7xl flex-col gap-10 px-5 py-6 md:px-14 md:py-16">
        <x-ui.page-header :kicker="__('app.contact.kicker')" kicker-lang="es" :title="__('app.contact.title')" />

        <x-contact.form :action="route('contact.store')" :reply-email="$replyEmail" />
    </div>
</x-layouts.app>
