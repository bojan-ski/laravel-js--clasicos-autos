<x-layouts.admin :title="__('app.admin.users.title')">
    <div class="mx-auto flex max-w-7xl flex-col gap-10 px-5 py-6 md:px-14 md:py-16">
        <div class="flex flex-col gap-5 md:flex-row md:items-end md:justify-between">
            <x-ui.page-header :kicker="__('app.admin.users.kicker')" kicker-lang="es" :title="__('app.admin.users.title')" />
            <x-admin.user-search :search="$search" />
        </div>

        @if ($users->isEmpty())
            <x-listings.empty-state :message="__('app.admin.users.empty')" />
        @else
            <div class="border border-rule bg-card">
                <table class="block w-full text-left text-sm text-ink lg:table">
                    <thead class="hidden lg:table-header-group">
                        <tr class="text-[11px] tracking-[0.12em] text-muted uppercase">
                            <th scope="col" class="px-3 py-3 font-normal">{{ __('app.admin.users.columns.id') }}</th>
                            <th scope="col" class="px-3 py-3 font-normal">{{ __('app.admin.users.columns.username') }}</th>
                            <th scope="col" class="px-3 py-3 font-normal">{{ __('app.admin.users.columns.email') }}</th>
                            <th scope="col" class="px-3 py-3 font-normal">{{ __('app.admin.users.columns.phone') }}</th>
                            <th scope="col" class="px-3 py-3 font-normal">{{ __('app.admin.users.columns.created') }}</th>
                            <th scope="col" class="px-3 py-3 font-normal">{{ __('app.admin.users.columns.status') }}</th>
                            <th scope="col" class="px-3 py-3 font-normal">{{ __('app.admin.users.columns.listings') }}</th>
                            <th scope="col" class="px-3 py-3 font-normal"><span class="sr-only">{{ __('app.admin.users.columns.actions') }}</span></th>
                        </tr>
                    </thead>
                    <tbody class="block lg:table-row-group">
                        @foreach ($users as $user)
                            <x-admin.user-row :user="$user" :return-query="$returnQuery" />
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        {{ $users->links() }}
    </div>
</x-layouts.admin>
