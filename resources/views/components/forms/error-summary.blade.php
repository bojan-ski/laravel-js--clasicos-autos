@if ($errors->any())
    <div role="alert" {{ $attributes->merge(['class' => 'flex items-start gap-2.5 border border-red-deep bg-red-deep/5 px-4 py-3 text-sm text-red-deep']) }}>
        <x-icons.circle-alert class="mt-0.5 size-4.5 shrink-0" />
        <ul class="flex flex-col gap-1">
            @foreach ($errors->all() as $message)
                <li>{{ $message }}</li>
            @endforeach
        </ul>
    </div>
@endif
