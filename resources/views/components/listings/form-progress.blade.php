@props(['count'])

<div data-form-progress aria-hidden="true" {{ $attributes->merge(['class' => 'flex gap-1 bg-paper py-2']) }}>
    @for ($segment = 0; $segment < $count; $segment++)
        <span data-form-progress-segment class="h-0.75 flex-1 bg-rule data-filled:bg-red"></span>
    @endfor
</div>
