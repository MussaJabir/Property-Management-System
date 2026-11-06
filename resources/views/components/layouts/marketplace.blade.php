@props(['title' => null, 'description' => null, 'image' => null, 'url' => null])

@php
    $whatsapp = 'https://wa.me/'.config('services.bjp.whatsapp').'?text='.rawurlencode(__('marketplace.cta_whatsapp_text'));
    $pageTitle = ($title ?? __('marketplace.title')).' · PMS';
    $shareTitle = ($title ?? __('marketplace.headline')).' · PMS';
    $shareDescription = $description ?: __('marketplace.meta_description');
    $pageUrl = $url ?? route('marketplace.browse');
    // Landlords open the app here too: one tap to their dashboard.
    $operator = auth('web')->user();
    $signedIn = $operator instanceof \App\Models\User && $operator->type === \App\Models\User::TYPE_OPERATOR;
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $pageTitle }}</title>
    <meta name="description" content="{{ $shareDescription }}">
    <link rel="canonical" href="{{ $pageUrl }}">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="PMS">
    <meta property="og:title" content="{{ $shareTitle }}">
    <meta property="og:description" content="{{ $shareDescription }}">
    <meta property="og:url" content="{{ $pageUrl }}">
    <meta property="og:image" content="{{ $image ?: asset('images/pwa/icon-512.png') }}">
    <meta name="theme-color" content="#0f766e">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        body.pms-market { font-family: 'Plus Jakarta Sans', ui-sans-serif, system-ui, sans-serif; }
        body.pms-market h1, body.pms-market h2 { letter-spacing: -0.02em; }
        .tnum { font-variant-numeric: tabular-nums; }
        [x-cloak] { display: none !important; }
        /* Colours the shared room parts and booking form read. */
        :root { --brand: #0f766e; --ink: #0f172a; --muted: #475569; --line: #e2e8f0; }
    </style>
</head>
<body class="pms-market min-h-screen bg-white text-slate-900 antialiased">

<header class="border-b border-slate-100">
    <div class="mx-auto flex max-w-7xl items-center justify-between gap-2 px-4 py-3 sm:gap-3 sm:px-6">
        <a href="{{ route('marketplace.browse') }}" class="flex shrink-0 items-center gap-2 text-slate-900 sm:gap-2.5">
            <span class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-teal-700 text-sm font-extrabold text-white">P</span>
            <span class="text-lg font-extrabold">PMS</span>
            <span class="hidden text-sm font-medium text-slate-500 sm:inline">{{ __('marketplace.find_a_place') }}</span>
        </a>
        <nav class="flex items-center gap-2.5 whitespace-nowrap text-sm font-semibold sm:gap-4">
            <a href="{{ route('home') }}" class="hidden text-slate-700 hover:text-slate-900 sm:inline">{{ __('marketplace.for_landlords') }}</a>
            @if ($signedIn)
                <a href="{{ route('filament.operator.pages.dashboard') }}" class="inline-flex min-h-[44px] items-center gap-1.5 text-slate-800 hover:text-slate-950">
                    <svg class="h-[18px] w-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/></svg>
                    <span class="sm:hidden">{{ __('marketplace.my_dashboard_short') }}</span>
                    <span class="max-sm:hidden">{{ __('marketplace.my_dashboard') }}</span>
                </a>
            @else
                <a href="{{ route('filament.operator.auth.login') }}" class="inline-flex min-h-[44px] items-center gap-1.5 text-slate-800 hover:text-slate-950">
                    <svg class="h-[18px] w-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><path d="M10 17l5-5-5-5"/><path d="M15 12H3"/></svg>
                    <span class="sm:hidden">{{ __('marketplace.landlord_login_short') }}</span>
                    <span class="max-sm:hidden">{{ __('marketplace.landlord_login') }}</span>
                </a>
            @endif
            <form method="POST" action="{{ route('locale.switch') }}" class="hidden sm:block">
                @csrf
                @if (app()->getLocale() === 'sw')
                    <button type="submit" name="locale" value="en" class="cursor-pointer text-slate-700 hover:text-slate-900">English</button>
                @else
                    <button type="submit" name="locale" value="sw" class="cursor-pointer text-slate-700 hover:text-slate-900">Kiswahili</button>
                @endif
            </form>
            <a href="{{ $whatsapp }}" target="_blank" rel="noopener" class="whitespace-nowrap rounded-full border border-teal-300 px-3 py-2.5 text-[13px] text-teal-800 sm:px-3.5 sm:text-sm hover:bg-teal-50 sm:border-transparent sm:bg-teal-700 sm:text-white sm:hover:bg-teal-800">{{ __('marketplace.list_property') }}</a>
        </nav>
    </div>
</header>

{{ $slot }}

<footer class="border-t border-slate-200">
    <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-3 px-4 py-5 text-xs text-slate-500 sm:px-6">
        <span>{{ __('marketplace.footer') }}</span>
        <span class="flex items-center gap-3">
            <a href="{{ route('privacy') }}" class="hover:text-slate-800">{{ __('privacy.footer_link') }}</a>
            <form method="POST" action="{{ route('locale.switch') }}" class="flex items-center gap-1.5">
                @csrf
                <button type="submit" name="locale" value="en" class="cursor-pointer {{ app()->getLocale() === 'en' ? 'font-bold text-slate-800' : 'hover:text-slate-800' }}">English</button>
                <span aria-hidden="true">·</span>
                <button type="submit" name="locale" value="sw" class="cursor-pointer {{ app()->getLocale() === 'sw' ? 'font-bold text-slate-800' : 'hover:text-slate-800' }}">Kiswahili</button>
            </form>
        </span>
    </div>
</footer>

</body>
</html>
