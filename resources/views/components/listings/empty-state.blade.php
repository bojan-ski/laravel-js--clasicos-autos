@props(['filtered' => false, 'message' => null, 'actionHref' => null, 'actionLabel' => null])

<div {{ $attributes->merge(['class' => 'flex flex-col items-center gap-4 border-2 border-dashed border-gold px-6 py-12 text-center']) }}>
    <p class="font-heading text-[30px] leading-tight italic">{{ $message ?? ($filtered ? __('app.listings.index.no_results') : __('app.listings.index.empty')) }}</p>
    @if ($filtered && $message === null)
        <a href="{{ route('listings.index') }}" class="text-red underline underline-offset-4 hover:no-underline">{{ __('app.listings.index.reset') }}</a>
    @endif
    @if ($actionHref !== null && $actionLabel !== null)
        <a href="{{ $actionHref }}" class="text-red underline underline-offset-4 hover:no-underline">{{ $actionLabel }}</a>
    @endif
</div>
