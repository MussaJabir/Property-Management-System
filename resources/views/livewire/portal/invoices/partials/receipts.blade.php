@php $receipts = $invoice->payments->map(fn ($p) => $p->receipt)->filter(); @endphp
@forelse ($receipts as $receipt)
    <a href="{{ url('/'.tenant()->slug.'/portal/invoices/'.$invoice->id.'/receipt/'.$receipt->id) }}"
       class="inline-flex min-h-[32px] items-center text-xs text-sky-600 hover:underline dark:text-sky-400">
        {{ $receipt->receipt_number ?? __('PDF') }}
    </a>
@empty
    <span class="text-xs text-zinc-400">—</span>
@endforelse
