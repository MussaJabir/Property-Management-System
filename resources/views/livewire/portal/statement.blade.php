<div class="flex flex-col gap-4">
    @php($fmt = fn (int $minor): string => number_format($minor / 100, 0, '.', ','))
    <div class="flex flex-col items-start justify-between gap-3 sm:flex-row sm:items-end">
        <h1 class="text-2xl font-semibold">{{ __('Statement') }}</h1>
        <div class="flex flex-wrap items-end gap-2 text-sm">
            <label class="flex flex-col gap-1">{{ __('From') }}<input type="date" wire:model.live="from" class="rounded-md border border-zinc-300 px-2 py-1.5 dark:border-zinc-700 dark:bg-zinc-900"></label>
            <label class="flex flex-col gap-1">{{ __('To') }}<input type="date" wire:model.live="to" class="rounded-md border border-zinc-300 px-2 py-1.5 dark:border-zinc-700 dark:bg-zinc-900"></label>
            <a href="{{ url('/'.tenant()?->getKey().'/portal/statement.pdf').'?from='.$from.'&to='.$to }}" target="_blank" rel="noopener"
               class="inline-flex min-h-[40px] items-center rounded-md bg-zinc-900 px-3 font-semibold text-white dark:bg-white dark:text-zinc-900">{{ __('Download PDF') }}</a>
        </div>
    </div>

    @if ($statement === null)
        <p class="text-sm text-zinc-500">{{ __('No statement yet.') }}</p>
    @else
        @php($closing = $statement['closing'])
        <div class="grid gap-3 sm:grid-cols-3">
            <div class="rounded-xl bg-white p-4 shadow-sm dark:bg-zinc-900"><p class="text-xs text-zinc-500">{{ __('Charged') }}</p><p class="text-lg font-semibold">{{ $statement['currency'] }} {{ $fmt($statement['charged']) }}</p></div>
            <div class="rounded-xl bg-white p-4 shadow-sm dark:bg-zinc-900"><p class="text-xs text-zinc-500">{{ __('Paid') }}</p><p class="text-lg font-semibold">{{ $statement['currency'] }} {{ $fmt($statement['paid']) }}</p></div>
            <div class="rounded-xl bg-white p-4 shadow-sm dark:bg-zinc-900"><p class="text-xs text-zinc-500">{{ __('Balance') }}</p>
                <p class="text-lg font-semibold">{{ $closing < 0 ? __('Credit :amount', ['amount' => $statement['currency'].' '.$fmt(abs($closing))]) : $statement['currency'].' '.$fmt($closing) }}</p></div>
        </div>

        <div class="overflow-x-auto rounded-xl bg-white shadow-sm dark:bg-zinc-900">
            <table class="w-full text-sm">
                <thead><tr class="border-b border-zinc-200 text-left text-zinc-500 dark:border-zinc-800">
                    <th class="px-3 py-2 font-medium">{{ __('Date') }}</th><th class="px-3 py-2 font-medium">{{ __('What') }}</th>
                    <th class="px-3 py-2 text-right font-medium">{{ __('Charged') }}</th><th class="px-3 py-2 text-right font-medium">{{ __('Paid') }}</th><th class="px-3 py-2 text-right font-medium">{{ __('Balance') }}</th>
                </tr></thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    <tr><td class="px-3 py-2 whitespace-nowrap">{{ $statement['from']->format('d/m/Y') }}</td><td class="px-3 py-2">{{ __('Opening balance') }}</td><td></td><td></td><td class="px-3 py-2 text-right tabular-nums">{{ $fmt($statement['opening']) }}</td></tr>
                    @foreach ($statement['rows'] as $row)
                        <tr @class(['bg-amber-50 dark:bg-amber-950/30' => $row['reversal']])>
                            <td class="px-3 py-2 whitespace-nowrap">{{ $row['date']->format('d/m/Y') }}</td>
                            <td class="px-3 py-2">{{ $row['what'] }} <span class="text-zinc-500">{{ $row['ref'] }}</span></td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ $row['charged'] !== null ? $fmt($row['charged']) : '' }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ $row['paid'] !== null ? ($row['paid'] < 0 ? '−'.$fmt(abs($row['paid'])) : $fmt($row['paid'])) : '' }}</td>
                            <td class="px-3 py-2 text-right font-semibold tabular-nums">{{ $row['balance'] < 0 ? '−'.$fmt(abs($row['balance'])) : $fmt($row['balance']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="text-xs text-zinc-500">{{ __('A minus balance is credit, used on your next invoice.') }}</p>
    @endif
</div>
