<x-layouts.admin :title="__('app.admin.feedback.title')">
    <div class="mx-auto flex max-w-7xl flex-col gap-10 px-5 py-6 md:px-14 md:py-16">
        <div class="flex flex-col gap-5">
            <x-ui.page-header :kicker="__('app.admin.feedback.kicker')" kicker-lang="es" :title="__('app.admin.feedback.title')" />
            <x-admin.feedback-status-chips :status="$status" />
        </div>

        @if ($contactMessages->isEmpty())
            <x-listings.empty-state :message="$status->emptyMessage()" />
        @else
            <div class="border border-rule bg-card">
                <table class="block w-full text-left text-sm text-ink lg:table">
                    <caption class="sr-only">{{ __('app.admin.feedback.title') }}</caption>
                    <thead class="hidden lg:table-header-group">
                        <tr class="text-[11px] tracking-[0.12em] text-muted uppercase">
                            <th scope="col" class="px-3 py-3 font-normal">{{ __('app.admin.feedback.columns.sender') }}</th>
                            <th scope="col" class="px-3 py-3 font-normal">{{ __('app.admin.feedback.columns.subject') }}</th>
                            <th scope="col" class="px-3 py-3 font-normal">{{ __('app.admin.feedback.columns.message') }}</th>
                            <th scope="col" class="px-3 py-3 font-normal">{{ __('app.admin.feedback.columns.received') }}</th>
                            <th scope="col" class="px-3 py-3 font-normal">{{ __('app.admin.feedback.columns.status') }}</th>
                            <th scope="col" class="px-3 py-3 font-normal"><span class="sr-only">{{ __('app.admin.feedback.columns.actions') }}</span></th>
                        </tr>
                    </thead>
                    <tbody class="block lg:table-row-group">
                        @foreach ($contactMessages as $contactMessage)
                            <x-admin.feedback-row :contact-message="$contactMessage" :return-query="$returnQuery" />
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        {{ $contactMessages->links() }}
    </div>
</x-layouts.admin>
