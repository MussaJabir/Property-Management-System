@props([
    'title' => null,
    'authenticated' => false,
])

@php
    $client = tenant();
    $brand = $client?->brand_primary_color ?: '#0F766E';
    $user = auth('renter')->user();
    $renter = $user?->renter;
    $clientSlug = $client?->slug;

    // Onboarding tour config (driver.js). Built here so all copy is
    // translatable; the tour accent matches the client's brand colour.
    $portalTourConfig = ($authenticated && $user) ? [
        'autostart' => $user->needsOnboarding(),
        'completeUrl' => url('/'.$clientSlug.'/portal/onboarding/complete'),
        'csrf' => csrf_token(),
        'labels' => [
            'next' => __('common.onboarding.next'),
            'previous' => __('common.onboarding.previous'),
            'done' => __('common.onboarding.done'),
            'progress' => __('common.onboarding.progress'),
        ],
        'steps' => [
            [
                'title' => __('common.onboarding.renter.welcome_title'),
                'description' => __('common.onboarding.renter.welcome_body'),
            ],
            [
                'element' => '[data-tour="renter-summary"]',
                'title' => __('common.onboarding.renter.summary_title'),
                'description' => __('common.onboarding.renter.summary_body'),
                'side' => 'bottom',
                'align' => 'center',
            ],
            [
                'element' => '[data-tour="renter-invoices"]',
                'title' => __('common.onboarding.renter.invoices_title'),
                'description' => __('common.onboarding.renter.invoices_body'),
                'side' => 'bottom',
                'align' => 'center',
            ],
            [
                'element' => '[data-tour="renter-maintenance"]',
                'title' => __('common.onboarding.renter.maintenance_title'),
                'description' => __('common.onboarding.renter.maintenance_body'),
                'side' => 'bottom',
                'align' => 'center',
            ],
            [
                'element' => '[data-tour="renter-notifications"]',
                'title' => __('common.onboarding.renter.notifications_title'),
                'description' => __('common.onboarding.renter.notifications_body'),
                'side' => 'bottom',
                'align' => 'end',
            ],
            [
                'element' => '[data-tour="renter-profile"]',
                'title' => __('common.onboarding.renter.profile_title'),
                'description' => __('common.onboarding.renter.profile_body'),
                'side' => 'bottom',
                'align' => 'center',
            ],
            [
                'title' => __('common.onboarding.renter.finish_title'),
                'description' => __('common.onboarding.renter.finish_body'),
            ],
        ],
    ] : null;
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ? $title.' · ' : '' }}{{ $client?->name ?? 'Portal' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @fluxAppearance
    <style>:root { --brand: {{ $brand }}; --pms-tour-accent: {{ $brand }}; --pms-tour-accent-hover: {{ $brand }}; }</style>
</head>
<body class="min-h-screen bg-zinc-50 text-zinc-900 antialiased dark:bg-zinc-950 dark:text-zinc-100">

@if ($authenticated && $user)
    <header class="border-b border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
        <div class="mx-auto flex max-w-5xl items-center justify-between px-4 py-3">
            <a href="{{ url('/'.$clientSlug.'/portal') }}" class="flex items-center gap-2 text-sm font-semibold">
                <span class="inline-flex h-7 w-7 items-center justify-center rounded-md text-white" style="background-color: var(--brand);">
                    {{ mb_substr($client?->name ?? 'P', 0, 1) }}
                </span>
                <span>{{ $client?->name }}</span>
            </a>
            <nav class="hidden gap-1 md:flex">
                @foreach ([
                    ['portal', __('Dashboard'), null],
                    ['portal/invoices', __('Invoices'), 'renter-invoices'],
                    ['portal/maintenance', __('Maintenance'), 'renter-maintenance'],
                    ['portal/profile', __('Profile'), 'renter-profile'],
                ] as [$path, $label, $tourKey])
                    @php
                        $href = url('/'.$clientSlug.'/'.$path);
                        $active = request()->is($clientSlug.'/'.$path) || ($path === 'portal' && request()->is($clientSlug.'/portal'));
                    @endphp
                    <a href="{{ $href }}"
                       @if ($tourKey) data-tour="{{ $tourKey }}" @endif
                       class="rounded-md px-3 py-1.5 text-sm {{ $active ? 'bg-zinc-100 font-semibold dark:bg-zinc-800' : 'text-zinc-600 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800' }}">
                        {{ $label }}
                    </a>
                @endforeach
            </nav>
            <div class="flex items-center gap-3 text-sm">
                <button type="button" onclick="window.pmsStartTour && window.pmsStartTour()"
                        title="{{ __('common.onboarding.replay') }}"
                        class="hidden rounded-md border border-zinc-300 px-2 py-1 text-xs text-zinc-600 hover:bg-zinc-100 md:inline-flex dark:border-zinc-700 dark:text-zinc-300 dark:hover:bg-zinc-800">
                    {{ __('common.onboarding.replay') }}
                </button>
                <span data-tour="renter-notifications"><livewire:portal.notifications-bell /></span>
                <form method="POST" action="{{ url('/'.$clientSlug.'/portal/locale') }}" class="hidden md:block">
                    @csrf
                    <select name="locale" onchange="this.form.submit()" class="rounded-md border border-zinc-300 bg-white px-2 py-1 text-xs dark:border-zinc-700 dark:bg-zinc-900">
                        <option value="en" @selected(app()->getLocale() === 'en')>EN</option>
                        <option value="sw" @selected(app()->getLocale() === 'sw')>SW</option>
                    </select>
                </form>
                <span class="hidden text-zinc-500 md:inline">{{ $user->name }}</span>
                <form method="POST" action="{{ url('/'.$clientSlug.'/portal/logout') }}">
                    @csrf
                    <button class="rounded-md border border-zinc-300 px-3 py-1 text-xs hover:bg-zinc-100 dark:border-zinc-700 dark:hover:bg-zinc-800">
                        {{ __('Sign out') }}
                    </button>
                </form>
            </div>
        </div>
        <nav class="flex gap-1 overflow-x-auto border-t border-zinc-200 px-2 py-2 md:hidden dark:border-zinc-800">
            @foreach ([
                ['portal', __('Dashboard')],
                ['portal/invoices', __('Invoices')],
                ['portal/maintenance', __('Maintenance')],
                ['portal/profile', __('Profile')],
            ] as [$path, $label])
                <a href="{{ url('/'.$clientSlug.'/'.$path) }}"
                   class="shrink-0 rounded-md px-3 py-1.5 text-xs {{ request()->is($clientSlug.'/'.$path) ? 'bg-zinc-100 font-semibold dark:bg-zinc-800' : 'text-zinc-600 dark:text-zinc-300' }}">
                    {{ $label }}
                </a>
            @endforeach
        </nav>
    </header>
@endif

<main class="mx-auto max-w-5xl px-4 py-6">
    @if (session('status'))
        <div class="mb-4 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-200">
            {{ session('status') }}
        </div>
    @endif

    {{ $slot }}
</main>

@if ($portalTourConfig)
    <script>
        window.pmsOnboarding = @json($portalTourConfig, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
    </script>
    @vite('resources/js/onboarding.js')
@endif

@fluxScripts
</body>
</html>
