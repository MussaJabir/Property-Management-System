<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Workspace not found · PMS</title>
    <style>
        body { font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; background: #f8fafc; color: #0f172a; margin: 0; padding: 0; }
        .wrap { max-width: 480px; margin: 10vh auto; padding: 2rem; text-align: center; }
        .badge { display: inline-block; padding: 0.25rem 0.75rem; background: #fef3c7; color: #78350f; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; letter-spacing: 0.05em; text-transform: uppercase; }
        h1 { font-size: 2rem; margin: 1rem 0 0.5rem; font-weight: 700; }
        p { color: #475569; line-height: 1.6; }
        .slug { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; background: #f1f5f9; padding: 0.125rem 0.5rem; border-radius: 0.25rem; color: #0f172a; }
        a.btn { display: inline-block; margin-top: 1.5rem; padding: 0.75rem 1.5rem; background: #0f766e; color: white; text-decoration: none; border-radius: 0.5rem; font-weight: 500; }
        a.btn:hover { background: #115e59; }
    </style>
</head>
<body>
    <div class="wrap">
        <span class="badge">404</span>
        <h1>Workspace not found</h1>
        <p>
            We couldn't find a tenant workspace for
            @if ($slug)
                <span class="slug">/{{ $slug }}</span>.
            @else
                this URL.
            @endif
        </p>
        <p>
            Check the URL with whoever invited you, or contact the BJP team if you think this is a mistake.
        </p>
        <a href="{{ url('/') }}" class="btn">Back to PMS home</a>
    </div>
</body>
</html>
