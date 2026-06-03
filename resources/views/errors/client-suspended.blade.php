<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Workspace suspended') }} · PMS</title>
    <style>
        body { font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; background: #f8fafc; color: #0f172a; margin: 0; padding: 0; }
        .wrap { max-width: 480px; margin: 10vh auto; padding: 2rem; text-align: center; }
        .badge { display: inline-block; padding: 0.25rem 0.75rem; background: #fee2e2; color: #991b1b; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; letter-spacing: 0.05em; text-transform: uppercase; }
        h1 { font-size: 2rem; margin: 1rem 0 0.5rem; font-weight: 700; }
        p { color: #475569; line-height: 1.6; }
        .name { font-weight: 600; color: #0f172a; }
        a.btn { display: inline-block; margin-top: 1.5rem; padding: 0.75rem 1.5rem; background: #0f766e; color: white; text-decoration: none; border-radius: 0.5rem; font-weight: 500; }
        a.btn:hover { background: #115e59; }
        .muted { margin-top: 1.25rem; font-size: 0.85rem; color: #94a3b8; }
    </style>
</head>
<body>
    <div class="wrap">
        <span class="badge">{{ __('Suspended') }}</span>
        <h1>{{ __('This workspace is suspended') }}</h1>
        <p>
            @if (! empty($clientName))
                <span class="name">{{ $clientName }}</span>
            @endif
            {{ __('is temporarily unavailable. This usually happens when a subscription needs to be renewed.') }}
        </p>
        <p>{{ __('Please contact the BJP Technologies team to restore access.') }}</p>
        @if (! empty($contactEmail))
            <a href="mailto:{{ $contactEmail }}" class="btn">{{ __('Contact support') }}</a>
        @else
            <a href="mailto:info@bjptechnologies.co.tz" class="btn">{{ __('Contact support') }}</a>
        @endif
        <p class="muted">PMS &middot; A BJP Technologies product</p>
    </div>
</body>
</html>
