<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('Statement') }} · {{ $statement['renter']->display_name }}</title>
    <style>
        @page { size: A4; margin: 16mm 14mm; }
        body { font-family: 'DejaVu Sans', Arial, sans-serif; color: #1f2937; font-size: 10pt; line-height: 1.45; }
        header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #0f766e; padding-bottom: 10px; margin-bottom: 16px; }
        .brand { font-size: 15pt; font-weight: 700; color: #0f766e; }
        .meta { text-align: right; font-size: 9pt; color: #4b5563; }
        h1 { font-size: 18pt; margin: 0 0 2px; color: #111827; }
        .sub { color: #4b5563; margin: 0 0 14px; }
        .totals { display: table; width: 100%; margin: 0 0 14px; }
        .totals div { display: table-cell; width: 33%; padding: 8px 10px; background: #f9fafb; border: 1px solid #e5e7eb; }
        .totals .label { font-size: 8.5pt; color: #6b7280; text-transform: uppercase; letter-spacing: 0.04em; }
        .totals .value { font-size: 12pt; font-weight: 700; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; font-size: 8.5pt; color: #4b5563; text-transform: uppercase; letter-spacing: 0.04em; border-bottom: 1px solid #d1d5db; padding: 6px; }
        td { padding: 6px; border-bottom: 1px solid #f3f4f6; vertical-align: top; }
        .num { text-align: right; white-space: nowrap; }
        tr.rev td { background: #fffbeb; }
        footer { margin-top: 16px; font-size: 8.5pt; color: #6b7280; }
    </style>
</head>
<body>
    @php
        $fmt = fn (int $minor): string => number_format($minor / 100, 0, '.', ',');
        $cur = $statement['currency'];
        $closing = $statement['closing'];
        $renter = $statement['renter'];
    @endphp

    <header>
        <div class="brand">{{ $client->name ?? 'PMS' }}</div>
        <div class="meta">{{ __('Issued') }}: {{ now()->format('d/m/Y') }}</div>
    </header>

    <h1>{{ __('Statement') }}</h1>
    <p class="sub">{{ $renter->display_name }} · {{ $statement['from']->format('d/m/Y') }} – {{ $statement['to']->format('d/m/Y') }}</p>

    <div class="totals">
        <div><div class="label">{{ __('Charged') }}</div><div class="value">{{ $cur }} {{ $fmt($statement['charged']) }}</div></div>
        <div><div class="label">{{ __('Paid') }}</div><div class="value">{{ $cur }} {{ $fmt($statement['paid']) }}</div></div>
        <div><div class="label">{{ __('Balance') }}</div><div class="value">{{ $closing < 0 ? __('Credit :amount', ['amount' => $cur.' '.$fmt(abs($closing))]) : $cur.' '.$fmt($closing) }}</div></div>
    </div>

    <table>
        <thead>
            <tr><th>{{ __('Date') }}</th><th>{{ __('What') }}</th><th>{{ __('Ref') }}</th><th class="num">{{ __('Charged') }}</th><th class="num">{{ __('Paid') }}</th><th class="num">{{ __('Balance') }}</th></tr>
        </thead>
        <tbody>
            <tr><td>{{ $statement['from']->format('d/m/Y') }}</td><td>{{ __('Opening balance') }}</td><td></td><td></td><td></td><td class="num">{{ $fmt($statement['opening']) }}</td></tr>
            @foreach ($statement['rows'] as $row)
                <tr @class(['rev' => $row['reversal']])>
                    <td>{{ $row['date']->format('d/m/Y') }}</td>
                    <td>{{ $row['what'] }}</td>
                    <td>{{ $row['ref'] }}</td>
                    <td class="num">{{ $row['charged'] !== null ? $fmt($row['charged']) : '' }}</td>
                    <td class="num">{{ $row['paid'] !== null ? ($row['paid'] < 0 ? '−'.$fmt(abs($row['paid'])) : $fmt($row['paid'])) : '' }}</td>
                    <td class="num">{{ $row['balance'] < 0 ? '−'.$fmt(abs($row['balance'])) : $fmt($row['balance']) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <footer>{{ __('Amounts in :currency. A minus balance is credit, used on the next invoice.', ['currency' => $cur]) }}</footer>
</body>
</html>
