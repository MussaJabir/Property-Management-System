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

    @error('pay')
        <div class="mt-4 rounded-md border border-red-200 bg-red-50 px-4 py-2 text-sm text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-200">
            {{ $message }}
        </div>
    @enderror

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
                            @php
                                $receipts = $invoice->payments->map(fn ($p) => $p->receipt)->filter();
                            @endphp
                            @forelse ($receipts as $receipt)
                                <a href="{{ url('/'.$client->slug.'/portal/invoices/'.$invoice->id.'/receipt/'.$receipt->id) }}"
                                   class="text-xs text-sky-600 hover:underline dark:text-sky-400">
                                    {{ $receipt->receipt_number ?? __('PDF') }}
                                </a><br>
                            @empty
                                <span class="text-xs text-zinc-400">—</span>
                            @endforelse
                        </td>
                    </tr>
                    @if ($canPayByPhone && $invoice->isOutstanding() && $invoice->currency === 'TZS' && $invoice->balanceDue() >= 100)
                        <tr class="bg-zinc-50/60 dark:bg-zinc-950/40">
                            <td colspan="7" class="px-4 py-3">
                                <form method="POST"
                                      action="{{ route('portal.invoices.pay', ['tenant' => tenant('id'), 'invoice' => $invoice->id]) }}"
                                      class="flex flex-wrap items-center gap-2">
                                    @csrf
                                    <span class="text-sm text-zinc-700 dark:text-zinc-300">
                                        {{ __('Pay :amount by mobile money:', ['amount' => $invoice->formatted_balance]) }}
                                    </span>
                                    <input type="tel" name="phone" required maxlength="20" inputmode="tel" autocomplete="tel"
                                           value="{{ old('phone', $payerPhone) }}"
                                           aria-label="{{ __('Mobile money number') }}"
                                           class="w-44 rounded-md border border-zinc-300 bg-white px-3 py-1.5 text-sm shadow-sm focus:border-zinc-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-950">
                                    <button type="submit"
                                            class="rounded-md bg-zinc-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-zinc-700 dark:bg-white dark:text-zinc-900 dark:hover:bg-zinc-200">
                                        {{ __('Send payment prompt') }}
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @endif
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
