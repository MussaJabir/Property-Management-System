<x-filament-panels::page>
    @php
        $fmt = fn (int $minor): string => number_format($minor / 100, 0, '.', ',');
        $cur = $statement['currency'];
        $closing = $statement['closing'];
    @endphp

    <div class="flex flex-col gap-6">
        {{ $this->filtersForm }}

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <x-filament::section compact>
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Charged') }}</p>
                <p class="text-xl font-bold">{{ $cur }} {{ $fmt($statement['charged']) }}</p>
            </x-filament::section>
            <x-filament::section compact>
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Paid') }}</p>
                <p class="text-xl font-bold">{{ $cur }} {{ $fmt($statement['paid']) }}</p>
            </x-filament::section>
            <x-filament::section compact>
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Balance') }}</p>
                <p @class(['text-xl font-bold', 'text-primary-700 dark:text-primary-400' => $closing <= 0, 'text-danger-700 dark:text-danger-400' => $closing > 0])>
                    {{ $closing < 0 ? __('Credit :amount', ['amount' => $cur.' '.$fmt(abs($closing))]) : $cur.' '.$fmt($closing) }}
                </p>
            </x-filament::section>
            <x-filament::section compact>
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Deposit held (not in balance)') }}</p>
                <p class="text-xl font-bold">{{ $cur }} {{ $fmt($statement['deposit_held']) }}</p>
            </x-filament::section>
        </div>

        <x-filament::section>
            <div class="-mx-6 -my-4 overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 text-left text-gray-500 dark:border-white/10 dark:text-gray-400">
                            <th class="px-4 py-3 font-medium">{{ __('Date') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('What') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('Ref') }}</th>
                            <th class="px-4 py-3 text-right font-medium">{{ __('Charged') }}</th>
                            <th class="px-4 py-3 text-right font-medium">{{ __('Paid') }}</th>
                            <th class="px-4 py-3 text-right font-medium">{{ __('Balance') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                        <tr>
                            <td class="px-4 py-3 whitespace-nowrap">{{ $statement['from']->format('d/m/Y') }}</td>
                            <td class="px-4 py-3">{{ __('Opening balance') }}</td>
                            <td></td><td></td><td></td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ $fmt($statement['opening']) }}</td>
                        </tr>
                        @forelse ($statement['rows'] as $row)
                            <tr @class(['bg-warning-50 dark:bg-warning-500/10' => $row['reversal']])>
                                <td class="px-4 py-3 whitespace-nowrap">{{ $row['date']->format('d/m/Y') }}</td>
                                <td class="px-4 py-3">
                                    {{ $row['what'] }}
                                    @if ($row['note'])
                                        <div class="text-xs text-warning-800 dark:text-warning-300">{{ $row['note'] }}</div>
                                    @endif
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">{{ $row['ref'] }}</td>
                                <td class="px-4 py-3 text-right tabular-nums">{{ $row['charged'] !== null ? $fmt($row['charged']) : '' }}</td>
                                <td class="px-4 py-3 text-right tabular-nums">{{ $row['paid'] !== null ? ($row['paid'] < 0 ? '−'.$fmt(abs($row['paid'])) : $fmt($row['paid'])) : '' }}</td>
                                <td class="px-4 py-3 text-right font-semibold tabular-nums">
                                    {{ $row['balance'] < 0 ? '−'.$fmt(abs($row['balance'])).' '.__('(credit)') : $fmt($row['balance']) }}
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-6 text-center text-gray-500 dark:text-gray-400">{{ __('Nothing charged or paid in these dates.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>
        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('The tenant sees the same statement in their portal.') }}</p>
    </div>
</x-filament-panels::page>
