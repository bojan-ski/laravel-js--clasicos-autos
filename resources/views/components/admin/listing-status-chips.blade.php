<nav data-brand-chips aria-label="{{ __('app.admin.listings.chips_label') }}" {{ $attributes->merge(['class' => '-mx-5 overflow-x-auto px-5 md:mx-0 md:overflow-visible md:px-0']) }}>
    <ul class="flex gap-2 md:flex-wrap">
        @foreach ($chips() as $chip)
            <li class="shrink-0">
                <a href="{{ $chip['url'] }}"
                   @if ($chip['active']) aria-current="true" @endif
                   @class([
                       'inline-flex min-h-11 items-center gap-1.5 rounded-full border px-4 text-[13px] whitespace-nowrap md:min-h-8.5',
                       'border-red text-red' => $chip['active'],
                       'border-rule text-ink-2 hover:border-rule-strong' => ! $chip['active'],
                   ])>@if ($chip['active'])<x-icons.check class="size-3.5 shrink-0" />@endif{{ $chip['label'] }}</a>
            </li>
        @endforeach
    </ul>
</nav>
