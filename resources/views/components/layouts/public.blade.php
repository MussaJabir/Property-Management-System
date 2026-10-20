@props(['title' => null])

@php
    use App\Models\CmsPage;
    use App\Support\BrandColor;

    $client = tenant();
    // Darkened when too bright for white text (e.g. a neon green).
    $brand = BrandColor::readable($client?->brand_primary_color);
    $whatsapp = $client?->whatsappUrl(__('Hello :name, I found you on your website.', ['name' => $client->name]));
    $current = request()->segment(2) ?: 'home';
    $clientSlug = $client?->slug;

    $nav = [
        CmsPage::SLUG_HOME => __('Home'),
        CmsPage::SLUG_UNITS => __('Rooms'),
        CmsPage::SLUG_ABOUT => __('About'),
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

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Figtree:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @fluxAppearance

    <style>
        :root { --brand: {{ $brand }}; --ink: #1d1d1b; --muted: #5e5c57; --line: #ebe9e4; color-scheme: light; }

        body.public-site {
            font-family: 'Figtree', ui-sans-serif, system-ui, sans-serif;
            background-color: #ffffff;
            color: var(--ink);
        }

        /* Older CMS blocks use these two classes: same family, heavier display. */
        .font-display { font-family: 'Figtree', ui-sans-serif, system-ui, sans-serif; font-weight: 800; letter-spacing: -0.02em; }
        .font-mono-ui { font-family: 'Figtree', ui-sans-serif, system-ui, sans-serif; font-variant-numeric: tabular-nums; }
        .tnum { font-variant-numeric: tabular-nums; }
        [x-cloak] { display: none !important; }

        .brand-rule { display: flex; align-items: center; justify-content: center; padding: 8px 0; }
        .brand-rule::before { content: ''; flex: 1; height: 1px; background: var(--line); }
        .brand-rule__dot { display: none; }
    </style>
</head>
<body class="public-site min-h-screen antialiased">

<header class="sticky top-0 z-30 border-b border-[var(--line)] bg-white/95 backdrop-blur">
    <div class="mx-auto flex max-w-6xl items-center gap-4 px-4 py-3 sm:gap-8 sm:px-6">
        <a href="{{ url('/'.$clientSlug) }}" class="flex min-w-0 items-center gap-2.5 text-[var(--ink)]">
            <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-[10px] text-base font-extrabold text-white" style="background-color: var(--brand);">
                {{ mb_substr($client?->name ?? 'S', 0, 1) }}
            </span>
            <span class="truncate text-[17px] font-bold">{{ $client?->name }}</span>
        </a>

        <nav class="hidden flex-1 items-center gap-6 md:flex">
            @foreach ($nav as $slug => $label)
                @php $active = $current === $slug; @endphp
                <a href="{{ url('/'.$clientSlug.($slug === 'home' ? '' : '/'.$slug)) }}"
                   class="border-b-2 pb-1 pt-1.5 text-[15px] font-medium {{ $active ? 'border-[var(--ink)] text-[var(--ink)]' : 'border-transparent text-[var(--muted)] hover:text-[var(--ink)]' }}">
                    {{ $label }}
                </a>
            @endforeach
        </nav>

        @if ($whatsapp)
            <a href="{{ $whatsapp }}" target="_blank" rel="noopener"
               class="ml-auto inline-flex min-h-[44px] shrink-0 items-center gap-2 rounded-full border border-[#d9d6cf] px-4 text-sm font-semibold text-[var(--ink)] hover:border-[var(--ink)] md:ml-0">
                <x-cms.whatsapp-icon class="h-[18px] w-[18px]" />
                <span class="hidden sm:inline">{{ __('WhatsApp us') }}</span>
                <span class="sm:hidden">{{ __('WhatsApp') }}</span>
            </a>
        @endif
    </div>

    <nav class="flex gap-5 overflow-x-auto border-t border-[var(--line)] px-4 py-2.5 md:hidden">
        @foreach ($nav as $slug => $label)
            @php $active = $current === $slug; @endphp
            <a href="{{ url('/'.$clientSlug.($slug === 'home' ? '' : '/'.$slug)) }}"
               class="shrink-0 border-b-2 pb-0.5 text-sm font-medium {{ $active ? 'border-[var(--ink)] text-[var(--ink)]' : 'border-transparent text-[var(--muted)]' }}">
                {{ $label }}
            </a>
        @endforeach
    </nav>
</header>

<main class="mx-auto max-w-6xl space-y-10 px-4 py-8 sm:px-6 sm:py-10 lg:space-y-14">
    @if (session('status'))
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
            {{ session('status') }}
        </div>
    @endif
    {{ $slot }}
</main>

<footer class="mt-16 border-t border-[var(--line)]">
    <div class="mx-auto grid max-w-6xl gap-8 px-4 py-10 sm:grid-cols-3 sm:px-6">
        <div class="space-y-2">
            <div class="text-base font-bold">{{ $client?->name }}</div>
            @if ($client?->contact_phone)
                <div class="text-sm text-[var(--muted)]">{{ $client->contact_phone }}</div>
            @endif
            @if ($client?->contact_email)
                <a href="mailto:{{ $client->contact_email }}" class="block text-sm text-[var(--muted)] underline underline-offset-4 hover:text-[var(--ink)]">{{ $client->contact_email }}</a>
            @endif
        </div>

        <ul class="space-y-2 text-sm">
            @foreach ($nav as $slug => $label)
                <li><a href="{{ url('/'.$clientSlug.($slug === 'home' ? '' : '/'.$slug)) }}" class="text-[var(--muted)] hover:text-[var(--ink)]">{{ $label }}</a></li>
            @endforeach
        </ul>

        <ul class="space-y-2 text-sm">
            <li><a href="{{ url('/'.$clientSlug.'/portal/login') }}" class="text-[var(--muted)] hover:text-[var(--ink)]">{{ __('Renter sign in') }}</a></li>
            <li><a href="{{ url('/manage/login') }}" class="text-[var(--muted)] hover:text-[var(--ink)]">{{ __('Owner sign in') }}</a></li>
        </ul>
    </div>
    <div class="mx-auto flex max-w-6xl flex-wrap justify-between gap-2 border-t border-[var(--line)] px-4 py-5 text-xs text-[var(--muted)] sm:px-6">
        <span>&copy; {{ now()->year }} {{ $client?->name }}</span>
        <span>{{ __('Powered by') }} <span class="font-semibold text-[var(--ink)]">PMS</span></span>
    </div>
</footer>

</body>
</html>
