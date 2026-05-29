<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('Invoice') }} {{ $invoice->invoice_number ?? '(draft)' }}</title>
    <style>
        @page { size: A4; margin: 18mm 16mm; }
        body { font-family: 'DejaVu Sans', Arial, sans-serif; color: #1f2937; font-size: 11pt; line-height: 1.45; }
        header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #0f766e; padding-bottom: 10px; margin-bottom: 18px; }
        .brand { font-size: 16pt; font-weight: 700; color: #0f766e; }
        .meta { text-align: right; font-size: 9.5pt; color: #4b5563; }
        h1 { font-size: 18pt; margin: 0 0 6px; text-align: center; letter-spacing: 1px; }
        h1.sub { font-size: 10.5pt; font-weight: 400; color: #6b7280; margin: 0 0 20px; text-align: center; }
        .grid { display: table; width: 100%; margin-bottom: 14px; }
        .grid .col { display: table-cell; width: 50%; vertical-align: top; padding-right: 18px; }
        .box { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 4px; padding: 10px 12px; }
        .box .label { font-size: 8.5pt; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 3px; }
        .box .value { font-size: 10.5pt; font-weight: 600; }
        table.items { width: 100%; border-collapse: collapse; margin-top: 6px; }
        table.items th, table.items td { padding: 8px 6px; text-align: left; font-size: 10pt; }
        table.items thead { background: #0f766e; color: #fff; }
        table.items th { font-weight: 600; }
        table.items tbody tr { border-bottom: 1px solid #e5e7eb; }
        table.items td.right, table.items th.right { text-align: right; }
        .totals { margin-top: 14px; display: table; width: 100%; }
        .totals .spacer { display: table-cell; width: 60%; }
        .totals .table { display: table-cell; width: 40%; }
        .totals table { width: 100%; border-collapse: collapse; }
        .totals td { padding: 6px 6px; font-size: 10.5pt; }
        .totals td.label { color: #4b5563; }
        .totals td.value { text-align: right; font-weight: 600; }
        .totals tr.grand td { border-top: 2px solid #0f766e; font-size: 12pt; padding-top: 8px; }
        .totals tr.balance td { color: #b91c1c; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 9pt; font-weight: 600; }
        .badge-paid { background: #d1fae5; color: #047857; }
        .badge-partial { background: #dbeafe; color: #1d4ed8; }
        .badge-unpaid { background: #fef3c7; color: #b45309; }
        .badge-overdue { background: #fee2e2; color: #b91c1c; }
        .notes { margin-top: 18px; background: #f9fafb; border-left: 3px solid #0f766e; padding: 8px 12px; font-size: 10pt; white-space: pre-wrap; }
        footer { position: fixed; bottom: 8mm; left: 16mm; right: 16mm; font-size: 9pt; color: #9ca3af; text-align: center; border-top: 1px solid #e5e7eb; padding-top: 6px; }
    </style>
</head>
<body>
    @php
        $badgeClass = match($invoice->status) {
            'paid' => 'badge-paid',
            'partial' => 'badge-partial',
            'overdue' => 'badge-overdue',
            default => 'badge-unpaid',
        };
        $fmt = fn ($cents) => $invoice->currency . ' ' . number_format(((int)$cents) / 100, 0, '.', ',');
    @endphp

    <header>
        <div class="brand">{{ $client->name ?? 'PMS' }}</div>
        <div class="meta">
            {{ __('Issued') }}: {{ optional($invoice->issued_at)->format('d/m/Y') ?? '—' }}<br>
            {{ __('Due') }}: {{ optional($invoice->due_date)->format('d/m/Y') ?? '—' }}<br>
            <span class="badge {{ $badgeClass }}">{{ strtoupper($invoice->status) }}</span>
        </div>
    </header>

    <h1>{{ __('INVOICE') }} {{ $invoice->invoice_number ?? '(DRAFT)' }}</h1>
    <h1 class="sub">{{ __('Ankara ya Malipo') }}</h1>

    <div class="grid">
        <div class="col">
            <div class="box">
                <div class="label">{{ __('Billed to') }}</div>
                <div class="value">{{ $invoice->lease?->renter?->display_name ?? '—' }}</div>
                <div style="font-size:9pt;color:#6b7280;margin-top:4px;">
                    {{ $invoice->lease?->renter?->phone ?? '' }}<br>
                    {{ $invoice->lease?->renter?->email ?? '' }}
                </div>
            </div>
        </div>
        <div class="col">
            <div class="box">
                <div class="label">{{ __('Unit') }}</div>
                <div class="value">
                    {{ $invoice->lease?->unit?->property?->name ?? '—' }} / {{ $invoice->lease?->unit?->code ?? '—' }}
                </div>
                <div style="font-size:9pt;color:#6b7280;margin-top:4px;">
                    {{ __('Period') }}: {{ optional($invoice->billing_period_start)->format('d/m/Y') }} → {{ optional($invoice->billing_period_end)->format('d/m/Y') }}
                </div>
            </div>
        </div>
    </div>

    <table class="items">
        <thead>
            <tr>
                <th style="width:50%;">{{ __('Description') }}</th>
                <th style="width:12%;">{{ __('Type') }}</th>
                <th class="right" style="width:10%;">{{ __('Qty') }}</th>
                <th class="right" style="width:14%;">{{ __('Unit price') }}</th>
                <th class="right" style="width:14%;">{{ __('Line total') }}</th>
            </tr>
        </thead>
        <tbody>
        @foreach ($invoice->items as $item)
            <tr>
                <td>{{ $item->description }}</td>
                <td>{{ ucfirst($item->type) }}</td>
                <td class="right">{{ rtrim(rtrim(number_format((float) $item->quantity, 2), '0'), '.') }}</td>
                <td class="right">{{ $fmt($item->unit_price) }}</td>
                <td class="right">{{ $fmt($item->line_total) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <div class="totals">
        <div class="spacer"></div>
        <div class="table">
            <table>
                <tr>
                    <td class="label">{{ __('Subtotal') }}</td>
                    <td class="value">{{ $fmt($invoice->subtotal) }}</td>
                </tr>
                @if ($invoice->tax_amount > 0)
                <tr>
                    <td class="label">{{ __('Tax') }}</td>
                    <td class="value">{{ $fmt($invoice->tax_amount) }}</td>
                </tr>
                @endif
                <tr class="grand">
                    <td class="label">{{ __('Total') }}</td>
                    <td class="value">{{ $fmt($invoice->total_amount) }}</td>
                </tr>
                @if ($invoice->amount_paid > 0)
                <tr>
                    <td class="label">{{ __('Paid') }}</td>
                    <td class="value">{{ $fmt($invoice->amount_paid) }}</td>
                </tr>
                @endif
                @if (! $invoice->isPaid())
                <tr class="balance">
                    <td class="label">{{ __('Balance due') }}</td>
                    <td class="value">{{ $fmt($invoice->balanceDue()) }}</td>
                </tr>
                @endif
            </table>
        </div>
    </div>

    @if ($invoice->notes)
        <div class="notes">{{ $invoice->notes }}</div>
    @endif

    <footer>
        {{ __('Generated by PMS — Property Management System') }} · {{ now()->format('d/m/Y H:i') }}
    </footer>
</body>
</html>
