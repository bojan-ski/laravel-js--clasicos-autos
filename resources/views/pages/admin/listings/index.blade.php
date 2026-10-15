<x-layouts.admin :title="__('app.admin.listings.title')">
    <div class="mx-auto flex max-w-7xl flex-col gap-10 px-5 py-6 md:px-14 md:py-16">
        <div class="flex flex-col gap-5">
            <div class="flex flex-col gap-5 md:flex-row md:items-end md:justify-between">
                <x-ui.page-header :kicker="__('app.admin.listings.kicker')" kicker-lang="es" :title="__('app.admin.listings.title')" />
                <x-admin.listing-search :search="$search" :filter="$filter" />
            </div>
            <x-admin.listing-status-chips :filter="$filter" :search="$search" />
        </div>

        @if ($listings->isEmpty())
            <x-listings.empty-state :message="$filter->emptyMessage($search, $listings->total())" />
        @else
            <div class="border border-rule bg-card">
                <table class="block w-full text-left text-sm text-ink lg:table">
                    <thead class="hidden lg:table-header-group">
                        <tr class="text-[11px] tracking-[0.12em] text-muted uppercase">
                            <th scope="col" class="px-3 py-3 font-normal">{{ __('app.admin.listings.columns.photo') }}</th>
                            <th scope="col" class="px-3 py-3 font-normal">{{ __('app.admin.listings.columns.listing') }}</th>
                            <th scope="col" class="px-3 py-3 font-normal">{{ __('app.admin.listings.columns.year') }}</th>
                            <th scope="col" class="px-3 py-3 font-normal">{{ __('app.admin.listings.columns.owner') }}</th>
                            <th scope="col" class="px-3 py-3 font-normal">{{ __('app.admin.listings.columns.submitted') }}</th>
                            <th scope="col" class="px-3 py-3 font-normal">{{ __('app.admin.listings.columns.status') }}</th>
                            <th scope="col" class="px-3 py-3 font-normal"><span class="sr-only">{{ __('app.admin.listings.columns.actions') }}</span></th>
                        </tr>
                    </thead>
                    <tbody class="block lg:table-row-group">
                        @foreach ($listings as $listing)
                            <x-admin.listing-row :listing="$listing" :return-query="$returnQuery" />
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        {{ $listings->links() }}
    </div>
</x-layouts.admin>
