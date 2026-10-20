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

    {{-- Phones: one card per invoice. --}}
    <div class="mt-4 flex flex-col gap-3 md:hidden">
        @forelse ($invoices as $invoice)
            <div class="rounded-xl bg-white p-4 shadow-sm dark:bg-zinc-900 dark:ring-1 dark:ring-white/10">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        @if ($invoice->balanceDue() > 0)
                            <p class="text-lg font-semibold">{{ $invoice->formatted_balance }}</p>
                            <p class="text-xs text-zinc-500">{{ __('of :total', ['total' => $invoice->formatted_total]) }}</p>
                        @else
                            <p class="text-lg font-semibold">{{ $invoice->formatted_total }}</p>
                            <p class="text-xs text-zinc-500">{{ __('Paid in full') }}</p>
                        @endif
                    </div>
                    @include('livewire.portal.invoices.partials.status')
                </div>
                <dl class="mt-3 grid grid-cols-2 gap-2 text-sm">
                    <div>
                        <dt class="text-xs text-zinc-500">{{ __('Due') }}</dt>
                        <dd>{{ optional($invoice->due_date)->format('d/m/Y') }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-zinc-500">{{ __('Period') }}</dt>
                        <dd>{{ optional($invoice->billing_period_start)->format('d/m') }} – {{ optional($invoice->billing_period_end)->format('d/m/Y') }}</dd>
                    </div>
                </dl>
                <div class="mt-3 flex flex-wrap items-center justify-between gap-2 border-t border-zinc-100 pt-2 text-xs text-zinc-500 dark:border-zinc-800">
                    <span>{{ $invoice->invoice_number ?? '—' }}</span>
                    <span class="flex flex-wrap items-center gap-2">{{ __('Receipts') }}: @include('livewire.portal.invoices.partials.receipts')</span>
                </div>
                <div class="mt-3 empty:hidden">@include('livewire.portal.invoices.partials.pay')</div>
            </div>
        @empty
            <p class="rounded-xl bg-white px-4 py-8 text-center text-sm text-zinc-500 shadow-sm dark:bg-zinc-900">{{ __('No invoices yet.') }}</p>
        @endforelse
    </div>

    {{-- Larger screens: the full table, scrolling inside its box if needed. --}}
    <div class="mt-4 hidden overflow-x-auto rounded-xl bg-white shadow-sm md:block dark:bg-zinc-900 dark:ring-1 dark:ring-white/10">
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
                        <td class="whitespace-nowrap px-4 py-3 font-medium">{{ $invoice->invoice_number ?? '—' }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-zinc-600 dark:text-zinc-300">
                            {{ optional($invoice->billing_period_start)->format('d/m/Y') }} →
                            {{ optional($invoice->billing_period_end)->format('d/m/Y') }}
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-zinc-600 dark:text-zinc-300">{{ optional($invoice->due_date)->format('d/m/Y') }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-right">{{ $invoice->formatted_total }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-right font-semibold">{{ $invoice->formatted_balance }}</td>
                        <td class="px-4 py-3">@include('livewire.portal.invoices.partials.status')</td>
                        <td class="px-4 py-3 text-right">@include('livewire.portal.invoices.partials.receipts')</td>
                    </tr>
                    @if ($canPayByPhone && $invoice->isOutstanding() && $invoice->currency === 'TZS' && $invoice->balanceDue() >= 100)
                        <tr class="bg-zinc-50/60 dark:bg-zinc-950/40">
                            <td colspan="7" class="px-4 py-3">@include('livewire.portal.invoices.partials.pay')</td>
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
