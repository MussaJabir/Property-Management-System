{{-- Received, paid on invoices, and what is left over as credit. --}}
@if ($show)
    @php($fmt = fn (int $minor): string => $currency.' '.number_format($minor / 100, 0, '.', ','))
    <div class="flex flex-col gap-2 rounded-xl border border-gray-200 p-4 text-sm dark:border-white/10">
        <div class="flex justify-between gap-3"><span class="text-gray-500 dark:text-gray-400">{{ __('Received') }}</span><b>{{ $fmt($received) }}</b></div>
        <div class="flex justify-between gap-3"><span class="text-gray-500 dark:text-gray-400">{{ __('Paid on invoices') }}</span><b>{{ $fmt(max(0, $onInvoices)) }}</b></div>
        @if ($credit > 0)
            <div class="flex justify-between gap-3 rounded-lg bg-primary-50 px-3 py-2 text-primary-800 dark:bg-primary-500/10 dark:text-primary-300">
                <span class="font-semibold">{{ $hasRenter ? __('Left over, kept as credit') : __('Left over') }}</span><b>{{ $fmt($credit) }}</b>
            </div>
            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $hasRenter ? __('Credit is used on the tenant\'s next invoice by itself.') : __('More than this invoice owes.') }}</p>
        @elseif ($credit < 0)
            <p class="rounded-lg bg-danger-50 px-3 py-2 font-semibold text-danger-700 dark:bg-danger-500/10 dark:text-danger-400">{{ __('The shares add up to more than the amount received.') }}</p>
        @endif
    </div>
@endif
