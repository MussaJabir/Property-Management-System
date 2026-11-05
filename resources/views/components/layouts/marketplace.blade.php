@props(['title' => null])

@php
    $whatsapp = 'https://wa.me/'.config('services.bjp.whatsapp').'?text='.rawurlencode(__('marketplace.cta_whatsapp_text'));
    $pageTitle = ($title ?? __('marketplace.title')).' · PMS';
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $pageTitle }}</title>
    <meta name="description" content="{{ __('marketplace.meta_description') }}">
    <link rel="canonical" href="{{ route('marketplace.browse') }}">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="PMS">
    <meta property="og:title" content="{{ __('marketplace.headline') }} · PMS">
    <meta property="og:description" content="{{ __('marketplace.meta_description') }}">
    <meta property="og:url" content="{{ route('marketplace.browse') }}">
    <meta property="og:image" content="{{ asset('images/pwa/icon-512.png') }}">
    <meta name="theme-color" content="#0f766e">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        body.pms-market { font-family: 'Plus Jakarta Sans', ui-sans-serif, system-ui, sans-serif; }
        body.pms-market h1, body.pms-market h2 { letter-spacing: -0.02em; }
        .tnum { font-variant-numeric: tabular-nums; }
    </style>
</head>
<body class="pms-market min-h-screen bg-white text-slate-900 antialiased">

<header class="border-b border-slate-100">
    <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-3 px-4 py-3 sm:px-6">
        <a href="{{ route('marketplace.browse') }}" class="flex items-center gap-2.5 text-slate-900">
            <span class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-teal-700 text-sm font-extrabold text-white">P</span>
            <span class="text-lg font-extrabold">PMS</span>
            <span class="hidden text-sm font-medium text-slate-500 sm:inline">{{ __('marketplace.find_a_place') }}</span>
        </a>
        <nav class="flex items-center gap-4 text-sm font-semibold">
            <a href="{{ route('home') }}" class="hidden text-slate-700 hover:text-slate-900 sm:inline">{{ __('marketplace.for_landlords') }}</a>
            <form method="POST" action="{{ route('locale.switch') }}" class="hidden sm:block">
                @csrf
                @if (app()->getLocale() === 'sw')
                    <button type="submit" name="locale" value="en" class="cursor-pointer text-slate-700 hover:text-slate-900">English</button>
                @else
                    <button type="submit" name="locale" value="sw" class="cursor-pointer text-slate-700 hover:text-slate-900">Kiswahili</button>
                @endif
            </form>
            <a href="{{ $whatsapp }}" target="_blank" rel="noopener" class="rounded-full border border-teal-300 px-3.5 py-2.5 text-teal-800 hover:bg-teal-50 sm:border-transparent sm:bg-teal-700 sm:text-white sm:hover:bg-teal-800">{{ __('marketplace.list_property') }}</a>
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
