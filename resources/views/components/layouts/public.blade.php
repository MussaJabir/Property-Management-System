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
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ? $title.' · ' : '' }}{{ $client?->name ?? 'Site' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @fluxAppearance
    <style>
        :root { --brand: {{ $brand }}; }
        /* Soft tinted background derived from the brand color via color-mix */
        .brand-bg-soft { background: color-mix(in srgb, var(--brand) 7%, white); }
        .brand-bg-soft-dark { background: color-mix(in srgb, var(--brand) 12%, #0a0a0a); }
        .brand-ring-soft { box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--brand) 25%, transparent); }
    </style>
</head>
<body class="min-h-screen bg-zinc-50 text-zinc-900 antialiased dark:bg-zinc-950 dark:text-zinc-100">

<header class="sticky top-0 z-30 border-b border-zinc-200/70 bg-white/90 backdrop-blur-md dark:border-zinc-800/70 dark:bg-zinc-900/85">
    <div class="mx-auto flex max-w-6xl items-center justify-between gap-3 px-4 py-3 sm:gap-6 sm:px-6">
        {{-- Brand --}}
        <a href="{{ url('/'.$clientSlug) }}" class="flex shrink-0 items-center gap-2 text-base font-bold">
            <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-white shadow-sm ring-1 ring-black/5" style="background-color: var(--brand);">
                {{ mb_substr($client?->name ?? 'S', 0, 1) }}
            </span>
            <span class="hidden truncate text-[15px] tracking-tight sm:inline">{{ $client?->name }}</span>
        </a>

        {{-- Desktop nav --}}
        <nav class="hidden flex-1 items-center justify-center gap-1 md:flex">
            @foreach ($nav as $slug => $label)
                @php $active = $current === $slug; @endphp
                <a href="{{ url('/'.$clientSlug.($slug === 'home' ? '' : '/'.$slug)) }}"
                   class="relative rounded-full px-4 py-1.5 text-sm font-medium transition-colors {{ $active ? 'text-zinc-900 dark:text-white' : 'text-zinc-600 hover:text-zinc-900 dark:text-zinc-300 dark:hover:text-white' }}"
                   @if ($active) style="background-color: color-mix(in srgb, var(--brand) 10%, transparent);" @endif>
                    {{ $label }}
                </a>
            @endforeach
        </nav>

        {{-- Sign-in buttons (both visible on every screen size; 44px touch target). --}}
        <div class="flex shrink-0 items-center gap-2">
            <a href="{{ url('/manage/login') }}"
               class="inline-flex min-h-[40px] cursor-pointer items-center rounded-full border border-zinc-300 px-3 text-xs font-semibold text-zinc-700 transition-colors hover:border-zinc-400 hover:bg-zinc-50 sm:min-h-[44px] sm:px-4 sm:text-sm dark:border-zinc-700 dark:text-zinc-200 dark:hover:border-zinc-600 dark:hover:bg-zinc-800">
                {{ __('Owner sign in') }}
            </a>
            <a href="{{ url('/'.$clientSlug.'/portal/login') }}"
               class="inline-flex min-h-[40px] cursor-pointer items-center rounded-full px-3 text-xs font-semibold text-white shadow-sm transition-opacity hover:opacity-90 sm:min-h-[44px] sm:px-4 sm:text-sm"
               style="background-color: var(--brand);">
                {{ __('Renter sign in') }}
            </a>
        </div>
    </div>

    {{-- Mobile nav strip --}}
    <nav class="flex gap-1 overflow-x-auto border-t border-zinc-200/70 bg-white/70 px-3 py-2 md:hidden dark:border-zinc-800/70 dark:bg-zinc-900/70">
        @foreach ($nav as $slug => $label)
            @php $active = $current === $slug; @endphp
            <a href="{{ url('/'.$clientSlug.($slug === 'home' ? '' : '/'.$slug)) }}"
               class="shrink-0 rounded-full px-3 py-1.5 text-xs font-medium transition-colors {{ $active ? 'text-zinc-900 dark:text-white' : 'text-zinc-600 dark:text-zinc-300' }}"
               @if ($active) style="background-color: color-mix(in srgb, var(--brand) 10%, transparent);" @endif>
                {{ $label }}
            </a>
        @endforeach
    </nav>
</header>

<main class="mx-auto max-w-6xl space-y-8 px-4 py-8 sm:px-6 sm:py-10">
    @if (session('status'))
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-sm font-medium text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-200">
            {{ session('status') }}
        </div>
    @endif
    {{ $slot }}
</main>

<footer class="mt-16 border-t border-zinc-200 bg-white/50 py-10 dark:border-zinc-800 dark:bg-zinc-900/50">
    <div class="mx-auto max-w-6xl px-4 sm:px-6">
        <div class="flex flex-col items-start justify-between gap-6 md:flex-row md:items-center">
            <div class="flex items-center gap-3">
                <span class="inline-flex h-8 w-8 items-center justify-center rounded-md text-white text-sm font-bold shadow-sm" style="background-color: var(--brand);">
                    {{ mb_substr($client?->name ?? 'S', 0, 1) }}
                </span>
                <div>
                    <div class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ $client?->name }}</div>
                    @if ($client?->contact_email)
                        <a href="mailto:{{ $client->contact_email }}" class="text-xs text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300">{{ $client->contact_email }}</a>
                    @endif
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-x-5 gap-y-1 text-xs text-zinc-500">
                @foreach ($nav as $slug => $label)
                    <a href="{{ url('/'.$clientSlug.($slug === 'home' ? '' : '/'.$slug)) }}"
                       class="hover:text-zinc-900 dark:hover:text-zinc-100">{{ $label }}</a>
                @endforeach
            </div>
        </div>
        <div class="mt-6 border-t border-zinc-200 pt-6 text-center text-xs text-zinc-500 dark:border-zinc-800">
            &copy; {{ now()->year }} {{ $client?->name }}. {{ __('All rights reserved.') }}
            <span class="mx-1 text-zinc-300">·</span>
            {{ __('Powered by') }} <span class="font-semibold text-zinc-600 dark:text-zinc-400">PMS</span>
        </div>
    </div>
</footer>

</body>
</html>
