<x-layouts.app :title="$heading">
    <div class="mx-auto flex max-w-7xl flex-col gap-10 px-5 py-6 md:px-14 md:py-16">
        <div class="flex flex-wrap items-center gap-6">
            <x-ui.stamp tone="red" aria-hidden="true" class="h-19 w-16 shrink-0 md:h-27 md:w-23">
                <span class="grid size-full place-items-center font-heading text-2xl text-red md:text-4xl">{{ $user->initials() }}</span>
            </x-ui.stamp>
            <x-ui.page-header :kicker="__('app.profile.kicker')" kicker-lang="es" :title="$heading" class="min-w-0 flex-1 wrap-break-word md:flex-none" />
            <x-account.delete-dialog :action="route('profile.destroy')" />
        </div>

        <div class="grid items-start gap-8 md:grid-cols-2">
            <x-account.details-card :user="$user" :account-created="$accountCreated" />
            <x-account.password-form :action="route('profile.password.update')" />
        </div>

        <section aria-labelledby="my-listings-heading" class="flex flex-col gap-6">
            <div class="flex flex-wrap items-baseline justify-between gap-4 border-b border-rule pb-2.5">
                <h2 id="my-listings-heading" class="text-[30px] leading-tight md:text-[34px]">{{ __('app.profile.listings.heading') }}</h2>
                @can('create', App\Models\CarListing::class)
                    <x-ui.button variant="primary" size="responsive" :href="route('listings.create')">{{ __('app.profile.listings.create') }}</x-ui.button>
                @endcan
            </div>

            @if ($listings->total() === 0)
                <x-listings.empty-state :message="__('app.profile.listings.empty')" />
            @else
                @if ($listings->isNotEmpty())
                    <div class="grid gap-9 md:grid-cols-2 lg:grid-cols-3">
                        @foreach ($listings as $listing)
                            <x-listings.card :listing="$listing" :show-status="true" :heading-level="3" />
                        @endforeach
                    </div>
                @endif

                {{ $listings->links() }}
            @endif
        </section>
    </div>
</x-layouts.app>
