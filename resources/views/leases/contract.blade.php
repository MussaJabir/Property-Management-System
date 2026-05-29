<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('Lease Agreement') }} — {{ $lease->id }}</title>
    <style>
        @page { size: A4; margin: 18mm 16mm; }
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            color: #1f2937;
            font-size: 11.5pt;
            line-height: 1.5;
        }
        header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #0f766e; padding-bottom: 10px; margin-bottom: 18px; }
        header .brand { font-size: 16pt; font-weight: 700; color: #0f766e; }
        header .meta { text-align: right; font-size: 9.5pt; color: #4b5563; }
        h1 { font-size: 18pt; margin: 0 0 4px; text-align: center; letter-spacing: 1px; }
        h1.sub { font-size: 11pt; font-weight: 400; color: #6b7280; margin: 0 0 22px; text-align: center; }
        h2 { font-size: 12pt; color: #0f766e; margin: 20px 0 6px; padding-bottom: 3px; border-bottom: 1px solid #e5e7eb; }
        table.fields { width: 100%; border-collapse: collapse; margin: 0; }
        table.fields td { padding: 4px 6px; vertical-align: top; font-size: 10.5pt; }
        table.fields td.label { color: #6b7280; width: 32%; }
        table.fields td.value { font-weight: 600; }
        .grid { display: table; width: 100%; }
        .grid .col { display: table-cell; width: 50%; vertical-align: top; padding-right: 18px; }
        .notes { background: #f9fafb; border-left: 3px solid #0f766e; padding: 8px 12px; font-size: 10.5pt; white-space: pre-wrap; }
        .signatures { margin-top: 48px; display: table; width: 100%; }
        .signatures .col { display: table-cell; width: 50%; padding-right: 24px; }
        .signatures .line { border-top: 1px solid #1f2937; margin-top: 60px; padding-top: 6px; font-size: 10pt; color: #4b5563; }
        footer { position: fixed; bottom: 8mm; left: 16mm; right: 16mm; font-size: 9pt; color: #9ca3af; text-align: center; border-top: 1px solid #e5e7eb; padding-top: 6px; }
    </style>
</head>
<body>
    <header>
        <div class="brand">{{ $client->name ?? 'PMS' }}</div>
        <div class="meta">
            {{ __('Generated') }}: {{ now()->format('d/m/Y H:i') }}<br>
            {{ __('Lease reference') }}: {{ \Illuminate\Support\Str::upper(substr($lease->id, 0, 8)) }}
        </div>
    </header>

    <h1>{{ __('LEASE AGREEMENT') }}</h1>
    <h1 class="sub">{{ __('Mkataba wa Kupanga') }}</h1>

    <h2>{{ __('Parties') }}</h2>
    <div class="grid">
        <div class="col">
            <table class="fields">
                <tr><td class="label">{{ __('Landlord') }}</td><td class="value">{{ $client->name ?? '—' }}</td></tr>
                <tr><td class="label">{{ __('Contact') }}</td><td class="value">{{ $client->contact_phone ?? '—' }}</td></tr>
                <tr><td class="label">{{ __('Email') }}</td><td class="value">{{ $client->contact_email ?? '—' }}</td></tr>
            </table>
        </div>
        <div class="col">
            <table class="fields">
                <tr><td class="label">{{ __('Renter') }}</td><td class="value">{{ $lease->renter?->display_name ?? '—' }}</td></tr>
                <tr><td class="label">{{ __('Phone') }}</td><td class="value">{{ $lease->renter?->phone ?? '—' }}</td></tr>
                <tr><td class="label">{{ __('Email') }}</td><td class="value">{{ $lease->renter?->email ?? '—' }}</td></tr>
            </table>
        </div>
    </div>

    <h2>{{ __('Property & Unit') }}</h2>
    <table class="fields">
        <tr>
            <td class="label">{{ __('Property') }}</td>
            <td class="value">{{ $lease->unit?->property?->name ?? '—' }}</td>
            <td class="label">{{ __('Unit code') }}</td>
            <td class="value">{{ $lease->unit?->code ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">{{ __('Unit type') }}</td>
            <td class="value">{{ ucfirst(str_replace('_', ' ', (string) ($lease->unit?->type ?? '—'))) }}</td>
            <td class="label">{{ __('Address') }}</td>
            <td class="value">{{ $lease->unit?->property?->address ?? '—' }}</td>
        </tr>
    </table>

    <h2>{{ __('Lease terms') }}</h2>
    <table class="fields">
        <tr>
            <td class="label">{{ __('Start date') }}</td>
            <td class="value">{{ optional($lease->start_date)->format('d/m/Y') ?? '—' }}</td>
            <td class="label">{{ __('End date') }}</td>
            <td class="value">{{ $lease->end_date ? $lease->end_date->format('d/m/Y') : __('Open-ended') }}</td>
        </tr>
        <tr>
            <td class="label">{{ __('Rent') }}</td>
            <td class="value">{{ $lease->formatted_rent }}</td>
            <td class="label">{{ __('Billing cycle') }}</td>
            <td class="value">{{ $lease->billing_cycle_label }}</td>
        </tr>
        <tr>
            <td class="label">{{ __('Deposit') }}</td>
            <td class="value">{{ $lease->formatted_deposit }}</td>
            <td class="label">{{ __('Payment due day') }}</td>
            <td class="value">{{ $lease->payment_due_day }}</td>
        </tr>
        <tr>
            <td class="label">{{ __('Status') }}</td>
            <td class="value">{{ ucfirst((string) $lease->status) }}</td>
            <td class="label">{{ __('Currency') }}</td>
            <td class="value">{{ $lease->currency }}</td>
        </tr>
    </table>

    @if ($lease->terms_notes)
        <h2>{{ __('Additional terms') }}</h2>
        <div class="notes">{{ $lease->terms_notes }}</div>
    @endif

    <div class="signatures">
        <div class="col">
            <div class="line">{{ __('Landlord signature & date') }}</div>
        </div>
        <div class="col">
            <div class="line">{{ __('Renter signature & date') }}</div>
        </div>
    </div>

    <footer>
        {{ __('Generated by PMS — Property Management System') }}
    </footer>
</body>
</html>
