@php
    $colors = [
        'paid' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300',
        'overdue' => 'bg-red-50 text-red-700 dark:bg-red-950 dark:text-red-300',
        'partial' => 'bg-sky-50 text-sky-700 dark:bg-sky-950 dark:text-sky-300',
        'unpaid' => 'bg-amber-50 text-amber-700 dark:bg-amber-950 dark:text-amber-300',
    ];
    $labels = [
        'paid' => __('Paid'),
        'overdue' => __('Overdue'),
        'partial' => __('Part paid'),
        'unpaid' => __('Unpaid'),
    ];
@endphp
<span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $colors[$invoice->status] ?? 'bg-zinc-100 text-zinc-700' }}">{{ $labels[$invoice->status] ?? ucfirst($invoice->status) }}</span>
