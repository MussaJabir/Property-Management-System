<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('privacy.title') }} · PMS</title>
    <meta name="description" content="{{ __('privacy.intro') }}">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        body.pms-privacy { font-family: 'Plus Jakarta Sans', ui-sans-serif, system-ui, sans-serif; }
        body.pms-privacy h1, body.pms-privacy h2 { letter-spacing: -0.02em; }
    </style>
</head>
<body class="pms-privacy bg-white text-slate-800 antialiased">

<header class="border-b border-slate-100">
    <div class="mx-auto flex max-w-3xl items-center justify-between px-5 py-4">
        <a href="/" class="flex items-center gap-2.5 text-lg font-bold text-slate-900">
            <span class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-teal-600 to-teal-700 text-sm font-extrabold text-white">P</span>
            <span>{{ __('landing.brand_name') }}</span>
        </a>
        <a href="/" class="text-sm font-medium text-teal-700 hover:text-teal-900">{{ __('privacy.back') }}</a>
    </div>
</header>

<main class="mx-auto max-w-3xl px-5 py-10 sm:py-14">
    <h1 class="text-3xl font-extrabold text-slate-900 sm:text-4xl">{{ __('privacy.title') }}</h1>
    <p class="mt-2 text-sm text-slate-500">{{ __('privacy.updated') }}</p>
    <p class="mt-6 leading-relaxed">{{ __('privacy.intro') }}</p>

    @php
        $sections = [
            ['roles_title', 'roles_body', null, null],
            ['collect_title', null, 'collect_items', null],
            ['use_title', null, 'use_items', 'use_note'],
            ['share_title', 'share_body', 'share_items', 'share_note'],
            ['app_title', 'app_body', null, null],
            ['keep_title', 'keep_body', null, null],
            ['security_title', 'security_body', null, null],
            ['rights_title', 'rights_body', null, null],
            ['children_title', 'children_body', null, null],
            ['changes_title', 'changes_body', null, null],
            ['contact_title', 'contact_body', null, null],
        ];
    @endphp

    @foreach ($sections as [$title, $body, $items, $note])
        <section class="mt-9">
            <h2 class="text-lg font-bold text-slate-900">{{ __('privacy.'.$title) }}</h2>
            @if ($body)
                <p class="mt-2 leading-relaxed">{{ __('privacy.'.$body) }}</p>
            @endif
            @if ($items)
                <ul class="mt-3 list-disc space-y-1.5 pl-5 leading-relaxed marker:text-teal-600">
                    @foreach ((array) __('privacy.'.$items) as $item)
                        <li>{{ $item }}</li>
                    @endforeach
                </ul>
            @endif
            @if ($note)
                <p class="mt-3 leading-relaxed">{{ __('privacy.'.$note) }}</p>
            @endif
        </section>
    @endforeach
</main>

<footer class="border-t border-slate-200">
    <div class="mx-auto max-w-3xl px-5 py-6 text-xs text-slate-500">
        &copy; {{ date('Y') }} {{ __('landing.footer_company') }}
    </div>
</footer>

</body>
</html>
