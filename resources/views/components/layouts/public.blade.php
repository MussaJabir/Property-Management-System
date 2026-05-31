@props(['title' => null])

@php
    use App\Models\CmsPage;

    $client = tenant();
    $brand = $client?->brand_primary_color ?: '#0F766E';
    $current = request()->segment(2) ?: 'home';
    $clientSlug = $client?->slug;

    $nav = [
        CmsPage::SLUG_HOME => __('Home'),
        CmsPage::SLUG_ABOUT => __('About'),
        CmsPage::SLUG_UNITS => __('Units'),
        CmsPage::SLUG_NEWS => __('News'),
        CmsPage::SLUG_CONTACT => __('Contact'),
    ];
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ? $title.' · ' : '' }}{{ $client?->name ?? 'Site' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @fluxAppearance
    <style>:root { --brand: {{ $brand }}; }</style>
</head>
<body class="min-h-screen bg-zinc-50 text-zinc-900 antialiased dark:bg-zinc-950 dark:text-zinc-100">

<header class="sticky top-0 z-30 border-b border-zinc-200 bg-white/95 backdrop-blur dark:border-zinc-800 dark:bg-zinc-900/95">
    <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-3">
        <a href="{{ url('/'.$clientSlug) }}" class="flex items-center gap-2 text-base font-semibold">
            <span class="inline-flex h-8 w-8 items-center justify-center rounded-md text-white" style="background-color: var(--brand);">
                {{ mb_substr($client?->name ?? 'S', 0, 1) }}
            </span>
            <span>{{ $client?->name }}</span>
        </a>
        <nav class="hidden gap-1 md:flex">
            @foreach ($nav as $slug => $label)
                <a href="{{ url('/'.$clientSlug.($slug === 'home' ? '' : '/'.$slug)) }}"
                   class="rounded-md px-3 py-1.5 text-sm {{ $current === $slug ? 'bg-zinc-100 font-semibold dark:bg-zinc-800' : 'text-zinc-600 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800' }}">
                    {{ $label }}
                </a>
            @endforeach
        </nav>
        <a href="{{ url('/'.$clientSlug.'/portal/login') }}"
           class="rounded-md px-4 py-2 text-xs font-semibold text-white shadow-sm transition hover:opacity-90"
           style="background-color: var(--brand);">
            {{ __('Renter sign in') }}
        </a>
    </div>
    <nav class="flex gap-1 overflow-x-auto border-t border-zinc-200 px-3 py-2 md:hidden dark:border-zinc-800">
        @foreach ($nav as $slug => $label)
            <a href="{{ url('/'.$clientSlug.($slug === 'home' ? '' : '/'.$slug)) }}"
               class="shrink-0 rounded-md px-3 py-1.5 text-xs {{ $current === $slug ? 'bg-zinc-100 font-semibold dark:bg-zinc-800' : 'text-zinc-600 dark:text-zinc-300' }}">
                {{ $label }}
            </a>
        @endforeach
    </nav>
</header>

<main class="mx-auto max-w-6xl space-y-8 px-4 py-8">
    @if (session('status'))
        <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-200">
            {{ session('status') }}
        </div>
    @endif
    {{ $slot }}
</main>

<footer class="mt-12 border-t border-zinc-200 py-8 dark:border-zinc-800">
    <div class="mx-auto max-w-6xl px-4 text-center text-xs text-zinc-500">
        © {{ now()->year }} {{ $client?->name }}. {{ __('All rights reserved.') }}
        @if ($client?->contact_email)
            · <a href="mailto:{{ $client->contact_email }}" class="hover:underline">{{ $client->contact_email }}</a>
        @endif
    </div>
</footer>

@fluxScripts
</body>
</html>
