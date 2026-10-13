@props(['rows'])

<dl {{ $attributes->merge(['class' => 'flex flex-col']) }}>
    @foreach ($rows as $row)
        <div class="flex justify-between gap-4 border-b border-rule-strong py-2 text-sm">
            <dt class="shrink-0 text-muted">{{ $row['label'] }}</dt>
            <dd class="min-w-0 text-right text-ink wrap-break-word">{{ $row['value'] }}</dd>
        </div>
    @endforeach
</dl>
