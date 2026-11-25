{{-- One unpaid invoice in "Where the money goes": what it is and what it still owes. --}}
<div class="flex min-h-[2.5rem] flex-col justify-center text-sm">
    <span class="font-semibold text-gray-950 dark:text-white">{{ $label }}</span>
    <span class="text-gray-500 dark:text-gray-400">{{ __('Still owed :amount', ['amount' => ($currency ?: 'TZS').' '.number_format((float) $owed, 0, '.', ',')]) }}</span>
</div>
