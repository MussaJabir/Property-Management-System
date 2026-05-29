<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('Receipt') }} {{ $receipt->receipt_number }}</title>
    <style>
        @page { size: A4; margin: 18mm 16mm; }
        body { font-family: 'DejaVu Sans', Arial, sans-serif; color: #1f2937; font-size: 11pt; line-height: 1.45; }
        header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #0f766e; padding-bottom: 10px; margin-bottom: 18px; }
        .brand { font-size: 16pt; font-weight: 700; color: #0f766e; }
        .meta { text-align: right; font-size: 9.5pt; color: #4b5563; }
        h1 { font-size: 22pt; margin: 18px 0 4px; text-align: center; letter-spacing: 2px; color: #0f766e; }
        h1.sub { font-size: 11pt; font-weight: 400; color: #6b7280; margin: 0 0 26px; text-align: center; }
        .number { text-align: center; font-size: 13pt; font-weight: 700; margin-bottom: 22px; }
        .grid { display: table; width: 100%; margin: 6px 0; }
        .grid .col { display: table-cell; width: 50%; vertical-align: top; padding-right: 18px; }
        .box { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 4px; padding: 10px 12px; }
        .box .label { font-size: 8.5pt; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 3px; }
        .box .value { font-size: 10.5pt; font-weight: 600; }
        .amount-banner { margin: 22px 0; padding: 16px; background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 6px; text-align: center; }
        .amount-banner .label { font-size: 9.5pt; color: #15803d; text-transform: uppercase; letter-spacing: 0.05em; }
        .amount-banner .value { font-size: 26pt; font-weight: 700; color: #047857; }
        table.details { width: 100%; border-collapse: collapse; margin-top: 10px; }
        table.details td { padding: 6px 8px; font-size: 10.5pt; border-bottom: 1px solid #f3f4f6; }
        table.details td.label { color: #6b7280; width: 35%; }
        table.details td.value { font-weight: 600; }
        .stamp { margin-top: 28px; text-align: center; color: #047857; font-size: 14pt; font-weight: 700; letter-spacing: 0.2em; border: 2px solid #047857; padding: 6px 16px; display: inline-block; }
        .stamp-wrap { text-align: center; }
        footer { position: fixed; bottom: 8mm; left: 16mm; right: 16mm; font-size: 9pt; color: #9ca3af; text-align: center; border-top: 1px solid #e5e7eb; padding-top: 6px; }
    </style>
</head>
<body>
    @php
        $payment = $receipt->payment;
        $invoice = $payment?->invoice;
        $fmt = fn ($cents, $currency = null) => ($currency ?? ($payment->currency ?? 'TZS')) . ' ' . number_format(((int)$cents) / 100, 0, '.', ',');
    @endphp

    <header>
        <div class="brand">{{ $client->name ?? 'PMS' }}</div>
        <div class="meta">
            {{ __('Issued') }}: {{ $receipt->issued_at?->format('d/m/Y H:i') ?? '—' }}
        </div>
    </header>

    <h1>{{ __('RECEIPT') }}</h1>
    <h1 class="sub">{{ __('Risiti ya Malipo') }}</h1>

    <div class="number">{{ $receipt->receipt_number }}</div>

    <div class="amount-banner">
        <div class="label">{{ __('Amount received') }}</div>
        <div class="value">{{ $fmt($payment?->amount ?? 0) }}</div>
    </div>

    <div class="grid">
        <div class="col">
            <div class="box">
                <div class="label">{{ __('Received from') }}</div>
                <div class="value">{{ $invoice?->lease?->renter?->display_name ?? '—' }}</div>
                <div style="font-size:9pt;color:#6b7280;margin-top:4px;">
                    {{ $invoice?->lease?->renter?->phone ?? '' }}
                </div>
            </div>
        </div>
        <div class="col">
            <div class="box">
                <div class="label">{{ __('For') }}</div>
                <div class="value">
                    {{ $invoice?->lease?->unit?->property?->name ?? '—' }} / {{ $invoice?->lease?->unit?->code ?? '—' }}
                </div>
                <div style="font-size:9pt;color:#6b7280;margin-top:4px;">
                    {{ __('Invoice') }} {{ $invoice?->invoice_number ?? '—' }}
                </div>
            </div>
        </div>
    </div>

    <table class="details">
        <tr>
            <td class="label">{{ __('Payment date') }}</td>
            <td class="value">{{ $payment?->payment_date?->format('d/m/Y') }}</td>
        </tr>
        <tr>
            <td class="label">{{ __('Method') }}</td>
            <td class="value">
                {{ str_replace('_', ' ', ucfirst((string) $payment?->method)) }}
                @if ($payment?->mobile_money_provider)
                    — {{ strtoupper($payment->mobile_money_provider) }}
                @endif
            </td>
        </tr>
        @if ($payment?->reference_number)
        <tr>
            <td class="label">{{ __('Reference') }}</td>
            <td class="value">{{ $payment->reference_number }}</td>
        </tr>
        @endif
        @if ($payment?->transaction_id)
        <tr>
            <td class="label">{{ __('Transaction ID') }}</td>
            <td class="value">{{ $payment->transaction_id }}</td>
        </tr>
        @endif
        @if ($payment?->receivedBy)
        <tr>
            <td class="label">{{ __('Received by') }}</td>
            <td class="value">{{ $payment->receivedBy->name }}</td>
        </tr>
        @endif
    </table>

    <div class="stamp-wrap">
        <div class="stamp">{{ __('PAID') }}</div>
    </div>

    <footer>
        {{ __('Generated by PMS — Property Management System') }} · {{ now()->format('d/m/Y H:i') }}
    </footer>
</body>
</html>
