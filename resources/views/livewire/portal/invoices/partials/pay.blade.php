@if ($canPayByPhone && $invoice->isOutstanding() && $invoice->currency === 'TZS' && $invoice->balanceDue() >= 100)
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
@endif
