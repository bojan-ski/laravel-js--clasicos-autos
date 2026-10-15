<x-layouts.admin :title="__('app.admin.reports.title')">
    <div class="mx-auto flex max-w-7xl flex-col gap-10 px-5 py-6 md:px-14 md:py-16">
        <div class="flex flex-col gap-5">
            <x-ui.page-header :kicker="__('app.admin.reports.kicker')" kicker-lang="es" :title="__('app.admin.reports.title')" />
            <x-admin.report-status-chips :status="$status" />
        </div>

        @if ($reports->isEmpty())
            <x-listings.empty-state :message="$status->emptyMessage()" />
        @else
            <div class="border border-rule bg-card">
                <table class="block w-full text-left text-sm text-ink lg:table">
                    <caption class="sr-only">{{ __('app.admin.reports.title') }}</caption>
                    <thead class="hidden lg:table-header-group">
                        <tr class="text-[11px] tracking-[0.12em] text-muted uppercase">
                            <th scope="col" class="px-3 py-3 font-normal">{{ __('app.admin.reports.columns.message') }}</th>
                            <th scope="col" class="px-3 py-3 font-normal">{{ __('app.admin.reports.columns.reason') }}</th>
                            <th scope="col" class="px-3 py-3 font-normal">{{ __('app.admin.reports.columns.reporter') }}</th>
                            <th scope="col" class="px-3 py-3 font-normal">{{ __('app.admin.reports.columns.reported_user') }}</th>
                            <th scope="col" class="px-3 py-3 font-normal">{{ __('app.admin.reports.columns.reported') }}</th>
                            <th scope="col" class="px-3 py-3 font-normal">{{ __('app.admin.reports.columns.status') }}</th>
                            <th scope="col" class="px-3 py-3 font-normal"><span class="sr-only">{{ __('app.admin.reports.columns.actions') }}</span></th>
                        </tr>
                    </thead>
                    <tbody class="block lg:table-row-group">
                        @foreach ($reports as $report)
                            <x-admin.report-row :report="$report" :return-query="$returnQuery" />
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        {{ $reports->links() }}
    </div>
</x-layouts.admin>
