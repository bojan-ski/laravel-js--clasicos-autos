@props(['rows', 'names'])

<div {{ $attributes->merge(['class' => 'rounded-xs border border-rule bg-card px-4 py-2 shadow-sm md:px-8']) }}>
    <table role="table" class="w-full border-collapse text-sm">
        <thead role="rowgroup" class="sr-only">
            <tr role="row">
                <th role="columnheader" scope="col">{{ $names[0] }}</th>
                <td role="cell"></td>
                @if (isset($names[1]))
                    <th role="columnheader" scope="col">{{ $names[1] }}</th>
                @else
                    <td role="cell"></td>
                @endif
            </tr>
        </thead>
        <tbody role="rowgroup">
            @foreach ($rows as $row)
                <tr role="row" class="grid grid-cols-2 gap-x-4 gap-y-1 border-b border-rule-strong py-2 last:border-b-0 md:table-row" @if ($row['differs']) data-differs @endif>
                    <td role="cell" class="text-left wrap-break-word md:w-[38%] md:py-2 {{ $row['differs'] ? 'text-red' : 'text-ink' }}">{{ $row['first'] }}</td>
                    <th role="rowheader" scope="row" class="order-first col-span-2 text-center text-[11px] font-normal tracking-[0.16em] text-gold-ink uppercase md:py-2">
                        @if ($row['differs'])
                            <span aria-hidden="true" class="text-red">≠</span>
                        @endif
                        {{ $row['label'] }}
                        @if ($row['differs'])
                            <span class="sr-only">{{ __('app.compare.differs') }}</span>
                        @endif
                    </th>
                    <td role="cell" class="text-right wrap-break-word md:w-[38%] md:py-2 {{ $row['differs'] ? 'text-red' : 'text-ink' }}">{{ $row['second'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
