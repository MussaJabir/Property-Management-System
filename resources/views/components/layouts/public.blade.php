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

    {{-- Editorial type pairing: Fraunces (display) + Plus Jakarta Sans (body) + IBM Plex Mono (numerals/labels). --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,500;9..144,600;9..144,700;9..144,800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=IBM+Plex+Mono:wght@400;500;600&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @fluxAppearance

    <style>
        :root { --brand: {{ $brand }}; color-scheme: light; }

        body.public-site {
            font-family: 'Plus Jakarta Sans', ui-sans-serif, system-ui, sans-serif;
            font-feature-settings: 'ss01' on, 'cv11' on;
            background-color: #fdfcf9;
            background-image:
                radial-gradient(at 8% 6%, color-mix(in srgb, var(--brand) 5%, transparent) 0%, transparent 35%),
                radial-gradient(at 92% 0%, color-mix(in srgb, var(--brand) 4%, transparent) 0%, transparent 40%),
                url("data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='2'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='0.045'/%3E%3C/svg%3E");
        }

        .font-display {
            font-family: 'Fraunces', 'Cormorant Garamond', Georgia, serif;
            font-feature-settings: 'ss01' on, 'ss03' on;
            font-variation-settings: 'opsz' 144, 'SOFT' 50;
            letter-spacing: -0.025em;
        }
        .font-mono-ui {
            font-family: 'IBM Plex Mono', ui-monospace, SFMono-Regular, Menlo, monospace;
            font-feature-settings: 'tnum' on, 'zero' on;
        }
        .tnum { font-variant-numeric: tabular-nums; }

        /* Section divider — hair-thin brand rule with center dot. */
        .brand-rule {
            display: flex; align-items: center; justify-content: center; gap: 12px; padding: 16px 0;
        }
        .brand-rule::before, .brand-rule::after {
            content: ''; flex: 1; height: 1px; max-width: 8rem;
            background: linear-gradient(to right, transparent, color-mix(in srgb, var(--brand) 35%, transparent), transparent);
        }
        .brand-rule__dot {
            width: 6px; height: 6px; border-radius: 9999px; background: var(--brand);
        }
    </style>
</head>
<body class="public-site min-h-screen text-zinc-900 antialiased">

{{-- ──────────────────────────────────────────────── NAV ──── --}}
<header class="sticky top-0 z-30 border-b border-zinc-900/[0.06] bg-[#fdfcf9]/85 backdrop-blur-md">
    <div class="mx-auto flex max-w-6xl items-center justify-between gap-3 px-4 py-3 sm:gap-6 sm:px-6">
        {{-- Brand --}}
        <a href="{{ url('/'.$clientSlug) }}" class="flex shrink-0 items-center gap-2.5 text-base font-bold">
            <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl text-white shadow-sm ring-1 ring-black/5 font-display text-base" style="background-color: var(--brand);">
                {{ mb_substr($client?->name ?? 'S', 0, 1) }}
            </span>
            <span class="hidden truncate font-display text-[17px] font-semibold tracking-tight text-zinc-900 sm:inline">
                {{ $client?->name }}
            </span>
        </a>

        {{-- Desktop nav --}}
        <nav class="hidden flex-1 items-center justify-center gap-1 md:flex">
            @foreach ($nav as $slug => $label)
                @php $active = $current === $slug; @endphp
                <a href="{{ url('/'.$clientSlug.($slug === 'home' ? '' : '/'.$slug)) }}"
                   class="relative cursor-pointer rounded-full px-4 py-2 text-[13px] font-semibold transition-colors {{ $active ? 'text-zinc-900' : 'text-zinc-500 hover:text-zinc-900' }}"
                   @if ($active) style="background-color: color-mix(in srgb, var(--brand) 12%, transparent);" @endif>
                    {{ $label }}
                </a>
            @endforeach
        </nav>

        {{-- Sign-in buttons (visible at every breakpoint, 44px touch target) --}}
        <div class="flex shrink-0 items-center gap-2">
            <a href="{{ url('/manage/login') }}"
               class="inline-flex min-h-[40px] cursor-pointer items-center rounded-full border border-zinc-900/15 px-3 text-[11px] font-bold uppercase tracking-[0.05em] text-zinc-700 transition-colors hover:border-zinc-900/30 hover:bg-white sm:min-h-[44px] sm:px-4 sm:text-xs">
                {{ __('Owner sign in') }}
            </a>
            <a href="{{ url('/'.$clientSlug.'/portal/login') }}"
               class="inline-flex min-h-[40px] cursor-pointer items-center rounded-full px-3 text-[11px] font-bold uppercase tracking-[0.05em] text-white shadow-sm transition-opacity hover:opacity-90 sm:min-h-[44px] sm:px-4 sm:text-xs"
               style="background-color: var(--brand);">
                {{ __('Renter sign in') }}
            </a>
        </div>
    </div>

    {{-- Mobile section nav --}}
    <nav class="flex gap-1 overflow-x-auto border-t border-zinc-900/[0.06] px-3 py-2 md:hidden">
        @foreach ($nav as $slug => $label)
            @php $active = $current === $slug; @endphp
            <a href="{{ url('/'.$clientSlug.($slug === 'home' ? '' : '/'.$slug)) }}"
               class="shrink-0 cursor-pointer rounded-full px-3 py-1.5 text-xs font-semibold transition-colors {{ $active ? 'text-zinc-900' : 'text-zinc-500' }}"
               @if ($active) style="background-color: color-mix(in srgb, var(--brand) 12%, transparent);" @endif>
                {{ $label }}
            </a>
        @endforeach
    </nav>
</header>

<main class="mx-auto max-w-6xl space-y-10 px-4 py-8 sm:px-6 sm:py-12 lg:space-y-16">
    @if (session('status'))
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
            {{ session('status') }}
        </div>
    @endif
    {{ $slot }}
</main>

{{-- ──────────────────────────────────────────────── FOOTER ──── --}}
<footer class="mt-16 border-t border-zinc-900/[0.08] bg-[#fdfcf9]">
    <div class="mx-auto max-w-6xl px-4 py-12 sm:px-6 sm:py-16">
        {{-- Brand rule divider --}}
        <div class="brand-rule -mt-4 mb-10"><span class="brand-rule__dot"></span></div>

        <div class="grid gap-8 sm:grid-cols-2 md:grid-cols-12">
            <div class="md:col-span-5">
                <div class="flex items-center gap-3">
                    <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl text-white text-base font-bold shadow-sm ring-1 ring-black/5 font-display" style="background-color: var(--brand);">
                        {{ mb_substr($client?->name ?? 'S', 0, 1) }}
                    </span>
                    <div>
                        <div class="font-display text-lg font-semibold text-zinc-900">{{ $client?->name }}</div>
                        <div class="text-xs uppercase tracking-[0.1em] text-zinc-500 font-mono-ui">{{ __('Property management') }}</div>
                    </div>
                </div>
                @if ($client?->contact_email)
                    <a href="mailto:{{ $client->contact_email }}" class="mt-5 inline-block text-sm text-zinc-700 underline decoration-zinc-300 underline-offset-4 transition-colors hover:decoration-zinc-900">
                        {{ $client->contact_email }}
                    </a>
                @endif
            </div>

            <div class="md:col-span-4">
                <h4 class="font-mono-ui text-[10px] font-semibold uppercase tracking-[0.15em] text-zinc-500">{{ __('Browse') }}</h4>
                <ul class="mt-4 space-y-2.5 text-sm">
                    @foreach ($nav as $slug => $label)
                        <li>
                            <a href="{{ url('/'.$clientSlug.($slug === 'home' ? '' : '/'.$slug)) }}"
                               class="text-zinc-700 transition-colors hover:text-zinc-900">{{ $label }}</a>
                        </li>
                    @endforeach
                </ul>
            </div>

            <div class="md:col-span-3">
                <h4 class="font-mono-ui text-[10px] font-semibold uppercase tracking-[0.15em] text-zinc-500">{{ __('Sign in') }}</h4>
                <ul class="mt-4 space-y-2.5 text-sm">
                    <li><a href="{{ url('/manage/login') }}" class="text-zinc-700 hover:text-zinc-900">{{ __('Owner sign in') }}</a></li>
                    <li><a href="{{ url('/'.$clientSlug.'/portal/login') }}" class="text-zinc-700 hover:text-zinc-900">{{ __('Renter sign in') }}</a></li>
                </ul>
            </div>
        </div>

        <div class="mt-12 flex flex-col items-start justify-between gap-3 border-t border-zinc-900/[0.08] pt-6 text-xs text-zinc-500 sm:flex-row sm:items-center">
            <span>&copy; {{ now()->year }} {{ $client?->name }} &middot; {{ __('All rights reserved.') }}</span>
            <span class="font-mono-ui text-[10px] uppercase tracking-[0.12em]">
                {{ __('Powered by') }} <span class="font-semibold text-zinc-700">PMS</span>
            </span>
        </div>
    </div>
</footer>

</body>
</html>
