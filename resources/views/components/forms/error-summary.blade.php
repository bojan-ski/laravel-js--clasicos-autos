@if ($errors->any())
    <div data-error-summary tabindex="-1" role="alert" aria-labelledby="error-summary-title" {{ $attributes->merge(['class' => 'flex items-start gap-2.5 border border-red-deep bg-red-deep/5 px-4 py-3 text-sm text-red-deep']) }}>
        <x-icons.circle-alert class="mt-0.5 size-4.5 shrink-0" />
        <div class="flex flex-col gap-1">
            <p id="error-summary-title" class="font-semibold">{{ trans_choice('app.forms.error_summary', count($errors->all()), ['count' => count($errors->all())]) }}</p>
            <ul class="flex flex-col md:gap-1">
                @foreach ($errors->messages() as $field => $messages)
                    @foreach ($messages as $message)
                        <li><a href="#{{ Str::before($field, '.') }}" class="inline-flex min-h-11 items-center underline underline-offset-2 hover:no-underline md:min-h-0">{{ $message }}</a></li>
                    @endforeach
                @endforeach
            </ul>
        </div>
    </div>
@endif
