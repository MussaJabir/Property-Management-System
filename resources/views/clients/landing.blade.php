<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $client->name }} · PMS</title>
    @php
        $brand = $client->brand_primary_color ?: '#0F766E';
        $statusColors = [
            'active' => ['#DCFCE7', '#14532D', 'Active'],
            'trial' => ['#FEF3C7', '#78350F', 'Trial'],
            'suspended' => ['#FEE2E2', '#7F1D1D', 'Suspended'],
            'cancelled' => ['#F3F4F6', '#1F2937', 'Cancelled'],
        ];
        [$badgeBg, $badgeFg, $badgeLabel] = $statusColors[$client->status] ?? $statusColors['cancelled'];
        $initials = collect(explode(' ', $client->name))->take(2)->map(fn ($w) => mb_substr($w, 0, 1))->implode('');
    @endphp
    <style>
        :root { --brand: {{ $brand }}; }
        * { box-sizing: border-box; }
        body { font-family: 'Inter', ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; background: #f8fafc; color: #0f172a; margin: 0; padding: 0; line-height: 1.5; }
        .topbar { background: var(--brand); color: white; padding: 1rem 2rem; display: flex; justify-content: space-between; align-items: center; }
        .topbar .brand { font-weight: 600; font-size: 0.875rem; letter-spacing: 0.05em; text-transform: uppercase; opacity: 0.85; }
        .topbar .locale { font-size: 0.75rem; opacity: 0.85; }
        .hero { background: linear-gradient(180deg, var(--brand) 0%, color-mix(in srgb, var(--brand) 85%, black) 100%); color: white; padding: 4rem 2rem 5rem; text-align: center; }
        .hero .avatar { width: 84px; height: 84px; border-radius: 50%; background: rgba(255,255,255,0.18); display: inline-flex; align-items: center; justify-content: center; font-size: 1.75rem; font-weight: 700; margin-bottom: 1rem; backdrop-filter: blur(4px); }
        .hero h1 { font-size: 2.5rem; font-weight: 700; margin: 0 0 0.5rem; }
        .hero p { opacity: 0.85; margin: 0; }
        .badge { display: inline-block; padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; letter-spacing: 0.025em; margin-top: 1rem; }
        .container { max-width: 960px; margin: -3rem auto 3rem; padding: 0 2rem; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1rem; }
        .card { background: white; border-radius: 0.75rem; padding: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.04), 0 4px 12px rgba(0,0,0,0.04); border: 1px solid #e2e8f0; }
        .card h3 { margin: 0 0 0.5rem; font-size: 1rem; color: #0f172a; }
        .card p { margin: 0; color: #64748b; font-size: 0.875rem; }
        .card.action { transition: transform 0.15s, box-shadow 0.15s; text-decoration: none; color: inherit; display: block; }
        .card.action:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
        .card.action .icon { width: 36px; height: 36px; border-radius: 8px; background: color-mix(in srgb, var(--brand) 15%, transparent); display: inline-flex; align-items: center; justify-content: center; color: var(--brand); margin-bottom: 0.75rem; font-size: 1.25rem; font-weight: 700; }
        .contact { display: flex; flex-direction: column; gap: 0.5rem; font-size: 0.875rem; }
        .contact a { color: var(--brand); text-decoration: none; }
        .contact a:hover { text-decoration: underline; }
        .muted { color: #94a3b8; }
        footer { text-align: center; padding: 2rem; color: #94a3b8; font-size: 0.75rem; }
    </style>
</head>
<body>
    <div class="topbar">
        <span class="brand">{{ $client->name }}</span>
        <span class="locale">EN · SW</span>
    </div>

    <section class="hero">
        @if ($client->logo_path)
            <img src="{{ \Illuminate\Support\Facades\Storage::disk('local')->url($client->logo_path) }}" alt="{{ $client->name }} logo" style="width:84px;height:84px;border-radius:50%;object-fit:cover;margin-bottom:1rem;">
        @else
            <div class="avatar">{{ strtoupper($initials) }}</div>
        @endif
        <h1>{{ $client->name }}</h1>
        <p>Property management workspace</p>
        <span class="badge" style="background: {{ $badgeBg }}; color: {{ $badgeFg }};">{{ $badgeLabel }}</span>
    </section>

    <main class="container">
        <div class="grid">
            <a href="/{{ $client->slug }}/manage" class="card action">
                <div class="icon">→</div>
                <h3>{{ __('common.dashboard') }} ({{ __('common.sign_in') }})</h3>
                <p>Property managers and accountants sign in here to manage units, leases, invoices, and payments.</p>
            </a>

            <a href="/{{ $client->slug }}/portal" class="card action">
                <div class="icon">@</div>
                <h3>{{ __('common.mpangaji') }} Portal</h3>
                <p>Renters log in to view invoices, pay rent, and submit maintenance requests.</p>
            </a>

            <div class="card">
                <h3>Contact</h3>
                <div class="contact" style="margin-top: 0.5rem;">
                    @if ($client->contact_email)
                        <span>✉ <a href="mailto:{{ $client->contact_email }}">{{ $client->contact_email }}</a></span>
                    @endif
                    @if ($client->contact_phone)
                        <span>☏ <a href="tel:{{ $client->contact_phone }}">{{ $client->contact_phone }}</a></span>
                    @endif
                    @if (! $client->contact_email && ! $client->contact_phone)
                        <span class="muted">No contact details on file yet.</span>
                    @endif
                </div>
            </div>

            <div class="card">
                <h3>About this workspace</h3>
                <p class="muted">Vacant units, news, and contact form will appear here once the CMS lands in Phase 9.</p>
            </div>
        </div>
    </main>

    <footer>
        Powered by <strong style="color: var(--brand);">PMS</strong> · Property Management System
    </footer>
</body>
</html>
