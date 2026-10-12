<x-layouts.app :title="__('app.profile.heading', ['username' => $user->username])">
    <div class="mx-auto max-w-7xl px-5 py-6 md:px-14 md:py-14">
        <x-ui.page-header :title="__('app.profile.heading', ['username' => $user->username])" />
    </div>
</x-layouts.app>
