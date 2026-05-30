@php $client = tenant(); @endphp

<div>
    <div class="flex flex-col items-start justify-between gap-3 sm:flex-row sm:items-center">
        <h1 class="text-2xl font-semibold">{{ __('Invoices') }}</h1>

        <div class="flex gap-1 rounded-md bg-zinc-100 p-1 text-xs dark:bg-zinc-900">
            @foreach ([
                'all' => __('All'),
                'open' => __('Open'),
                'paid' => __('Paid'),
            ] as $value => $label)
                <button wire:click="$set('status', '{{ $value }}')"
                        class="rounded px-3 py-1 {{ $status === $value ? 'bg-white shadow-sm dark:bg-zinc-700' : 'text-zinc-600 dark:text-zinc-300' }}">
                    {{ $label }}
                </button>
            @endforeach
        </div>
    </div>

    <div class="mt-4 overflow-hidden rounded-xl bg-white shadow-sm dark:bg-zinc-900 dark:ring-1 dark:ring-white/10">
        <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-800">
            <thead class="bg-zinc-50 text-xs uppercase tracking-wider text-zinc-500 dark:bg-zinc-950">
                <tr>
                    <th class="px-4 py-3 text-left font-medium">{{ __('Number') }}</th>
                    <th class="px-4 py-3 text-left font-medium">{{ __('Period') }}</th>
                    <th class="px-4 py-3 text-left font-medium">{{ __('Due') }}</th>
                    <th class="px-4 py-3 text-right font-medium">{{ __('Total') }}</th>
                    <th class="px-4 py-3 text-right font-medium">{{ __('Balance') }}</th>
                    <th class="px-4 py-3 text-left font-medium">{{ __('Status') }}</th>
                    <th class="px-4 py-3 text-right font-medium">{{ __('Receipts') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                @forelse ($invoices as $invoice)
                    <tr>
                        <td class="px-4 py-3 font-medium">{{ $invoice->invoice_number ?? '—' }}</td>
                        <td class="px-4 py-3 text-zinc-600 dark:text-zinc-300">
                            {{ optional($invoice->billing_period_start)->format('d/m/Y') }} →
                            {{ optional($invoice->billing_period_end)->format('d/m/Y') }}
                        </td>
                        <td class="px-4 py-3 text-zinc-600 dark:text-zinc-300">{{ optional($invoice->due_date)->format('d/m/Y') }}</td>
                        <td class="px-4 py-3 text-right">{{ $invoice->formatted_total }}</td>
                        <td class="px-4 py-3 text-right font-semibold">{{ $invoice->formatted_balance }}</td>
                        <td class="px-4 py-3">
                            @php
                                $colors = [
                                    'paid' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300',
                                    'overdue' => 'bg-red-50 text-red-700 dark:bg-red-950 dark:text-red-300',
                                    'partial' => 'bg-sky-50 text-sky-700 dark:bg-sky-950 dark:text-sky-300',
                                    'unpaid' => 'bg-amber-50 text-amber-700 dark:bg-amber-950 dark:text-amber-300',
                                ];
                                $cls = $colors[$invoice->status] ?? 'bg-zinc-100 text-zinc-700';
                            @endphp
                            <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $cls }}">{{ ucfirst($invoice->status) }}</span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            @forelse ($invoice->receipts as $receipt)
                                <a href="{{ url('/'.$client->slug.'/portal/invoices/'.$invoice->id.'/receipt/'.$receipt->id) }}"
                                   class="text-xs text-sky-600 hover:underline dark:text-sky-400">
                                    {{ $receipt->receipt_number ?? __('PDF') }}
                                </a><br>
                            @empty
                                <span class="text-xs text-zinc-400">—</span>
                            @endforelse
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-sm text-zinc-500">
                            {{ __('No invoices yet.') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $invoices->links() }}
    </div>
</div>
