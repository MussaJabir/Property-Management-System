@props([
    'badge' => 'ERROR',
    'badgeBg' => '#fef3c7',
    'badgeFg' => '#78350f',
    'title' => 'Something went wrong',
])

<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} · PMS</title>
    <style>
        body { font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; background: #f8fafc; color: #0f172a; margin: 0; padding: 0; }
        .wrap { max-width: 520px; margin: 10vh auto; padding: 2rem; text-align: center; }
        .badge { display: inline-block; padding: 0.25rem 0.75rem; background: {{ $badgeBg }}; color: {{ $badgeFg }}; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; letter-spacing: 0.05em; text-transform: uppercase; }
        h1 { font-size: 2rem; margin: 1rem 0 0.5rem; font-weight: 700; }
        p { color: #475569; line-height: 1.65; }
        .mono { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; background: #f1f5f9; padding: 0.125rem 0.5rem; border-radius: 0.25rem; color: #0f172a; font-size: 0.9em; }
        a.btn { display: inline-block; margin-top: 1.5rem; padding: 0.75rem 1.5rem; background: #0f766e; color: white; text-decoration: none; border-radius: 0.5rem; font-weight: 500; }
        a.btn:hover { background: #115e59; }
        @media (prefers-color-scheme: dark) {
            body { background: #09090b; color: #f4f4f5; }
            p { color: #a1a1aa; }
            .mono { background: #18181b; color: #f4f4f5; }
        }
    </style>
</head>
<body>
    <div class="wrap">
        <span class="badge">{{ $badge }}</span>
        {{ $slot }}
    </div>
</body>
</html>
