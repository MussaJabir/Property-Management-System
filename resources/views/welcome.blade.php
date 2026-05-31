<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="{{ __('landing.meta_description') }}">
    <meta property="og:title" content="{{ __('landing.meta_title') }}">
    <meta property="og:description" content="{{ __('landing.meta_description') }}">
    <meta property="og:type" content="website">
    <title>{{ __('landing.meta_title') }}</title>

    {{-- Plus Jakarta Sans — scoped to this page so the Filament admin keeps its Instrument Sans. --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        body.pms-landing { font-family: 'Plus Jakarta Sans', ui-sans-serif, system-ui, sans-serif; }
        body.pms-landing h1, body.pms-landing h2, body.pms-landing h3 { letter-spacing: -0.025em; }
        .pms-grid-bg {
            background-image:
                linear-gradient(to right, rgba(15, 118, 110, 0.06) 1px, transparent 1px),
                linear-gradient(to bottom, rgba(15, 118, 110, 0.06) 1px, transparent 1px);
            background-size: 56px 56px;
        }
        @media (prefers-reduced-motion: reduce) {
            .pms-fade-in { opacity: 1 !important; transform: none !important; }
        }
    </style>
</head>
<body class="pms-landing bg-white text-slate-900 antialiased selection:bg-teal-200">

{{-- ════════════════════════════════════════════════════════════════ NAV ══ --}}
<header class="sticky top-0 z-50 border-b border-slate-100 bg-white/85 backdrop-blur-md">
    <div class="mx-auto flex max-w-7xl items-center justify-between px-5 py-4 sm:px-6 lg:px-8">
        <a href="/" class="flex items-center gap-2.5 text-lg font-bold text-slate-900">
            <span class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-teal-600 to-teal-700 text-sm font-extrabold text-white shadow-sm ring-1 ring-teal-700/20">P</span>
            <span class="text-[17px] tracking-tight">{{ __('landing.brand_name') }}</span>
        </a>

        <nav class="hidden items-center gap-8 text-sm font-medium text-slate-600 md:flex">
            <a href="#features" class="transition-colors hover:text-slate-900">{{ __('landing.nav_features') }}</a>
            <a href="#how" class="transition-colors hover:text-slate-900">{{ __('landing.nav_how') }}</a>
            <a href="#faq" class="transition-colors hover:text-slate-900">{{ __('landing.nav_faq') }}</a>
        </nav>

        <div class="flex items-center gap-2 sm:gap-3">
            {{-- Language toggle --}}
            @if (Route::has('locale.switch'))
                <form method="POST" action="{{ route('locale.switch') }}" class="hidden items-center rounded-full border border-slate-200 bg-white p-0.5 text-xs font-semibold text-slate-500 sm:flex">
                    @csrf
                    <button type="submit" name="locale" value="en" class="cursor-pointer rounded-full px-3 py-1 transition-colors {{ app()->getLocale() === 'en' ? 'bg-slate-900 text-white' : 'hover:text-slate-900' }}">
                        {{ __('landing.lang_en') }}
                    </button>
                    <button type="submit" name="locale" value="sw" class="cursor-pointer rounded-full px-3 py-1 transition-colors {{ app()->getLocale() === 'sw' ? 'bg-slate-900 text-white' : 'hover:text-slate-900' }}">
                        {{ __('landing.lang_sw') }}
                    </button>
                </form>
            @endif

            <a href="/admin/login" class="hidden cursor-pointer text-sm font-medium text-slate-700 transition-colors hover:text-slate-900 lg:inline-flex">
                {{ __('landing.cta_sign_in') }}
            </a>
            <a href="mailto:{{ __('landing.footer_email') }}?subject=PMS%20demo%20request" class="inline-flex cursor-pointer items-center gap-1.5 rounded-full bg-slate-900 px-4 py-2 text-sm font-semibold text-white shadow-sm ring-1 ring-slate-900/20 transition-colors hover:bg-slate-700">
                {{ __('landing.cta_demo') }}
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4"><path fill-rule="evenodd" d="M3 10a.75.75 0 0 1 .75-.75h10.638L10.23 5.29a.75.75 0 1 1 1.04-1.08l5.5 5.25a.75.75 0 0 1 0 1.08l-5.5 5.25a.75.75 0 1 1-1.04-1.08l4.158-3.96H3.75A.75.75 0 0 1 3 10Z" clip-rule="evenodd" /></svg>
            </a>
        </div>
    </div>
</header>

{{-- ═══════════════════════════════════════════════════════════════ HERO ══ --}}
<section class="relative overflow-hidden">
    {{-- Background: soft teal wash + grid pattern --}}
    <div class="absolute inset-0 -z-10 bg-gradient-to-b from-teal-50/70 via-white to-white"></div>
    <div class="absolute inset-x-0 top-0 -z-10 h-[700px] pms-grid-bg [mask-image:radial-gradient(ellipse_at_top,black_30%,transparent_70%)]"></div>
    {{-- Decorative glow --}}
    <div class="absolute left-1/2 top-32 -z-10 h-72 w-[36rem] -translate-x-1/2 rounded-full bg-teal-400/20 blur-3xl"></div>

    <div class="mx-auto max-w-7xl px-5 pt-16 pb-20 sm:px-6 sm:pt-20 lg:px-8 lg:pt-28 lg:pb-28">
        <div class="mx-auto max-w-4xl text-center">
            <span class="inline-flex animate-pulse items-center gap-2 rounded-full bg-white px-3 py-1 text-xs font-semibold text-teal-700 shadow-sm ring-1 ring-teal-200/60">
                <span class="relative flex h-2 w-2">
                    <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-teal-500 opacity-75"></span>
                    <span class="relative inline-flex h-2 w-2 rounded-full bg-teal-600"></span>
                </span>
                {{ __('landing.hero_eyebrow') }}
            </span>

            <h1 class="mt-6 text-[2.5rem] font-extrabold leading-[1.05] tracking-tight text-slate-900 sm:text-5xl lg:text-7xl">
                {{ __('landing.hero_title_line_1') }}<br>
                <span class="relative inline-block">
                    <span class="bg-gradient-to-br from-teal-600 to-teal-800 bg-clip-text text-transparent">{{ __('landing.hero_title_line_2') }}</span>
                    <span aria-hidden="true" class="absolute -bottom-1 left-0 right-0 -z-10 h-3 bg-teal-100"></span>
                </span>
            </h1>

            <p class="mx-auto mt-6 max-w-2xl text-base leading-7 text-slate-600 sm:text-lg sm:leading-8">
                {{ __('landing.hero_subtitle') }}
            </p>

            <div class="mt-10 flex flex-wrap items-center justify-center gap-3 sm:gap-4">
                <a href="mailto:{{ __('landing.footer_email') }}?subject=PMS%20demo%20request" class="inline-flex cursor-pointer items-center gap-2 rounded-full bg-teal-600 px-6 py-3.5 text-base font-semibold text-white shadow-lg shadow-teal-600/25 ring-1 ring-teal-700/20 transition-all hover:bg-teal-500 hover:shadow-teal-600/40">
                    {{ __('landing.hero_cta_primary') }}
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4"><path fill-rule="evenodd" d="M3 10a.75.75 0 0 1 .75-.75h10.638L10.23 5.29a.75.75 0 1 1 1.04-1.08l5.5 5.25a.75.75 0 0 1 0 1.08l-5.5 5.25a.75.75 0 1 1-1.04-1.08l4.158-3.96H3.75A.75.75 0 0 1 3 10Z" clip-rule="evenodd" /></svg>
                </a>
                <a href="#showcase" class="inline-flex cursor-pointer items-center gap-2 rounded-full bg-white px-6 py-3.5 text-base font-semibold text-slate-700 shadow-sm ring-1 ring-slate-300 transition-colors hover:text-slate-900 hover:ring-slate-400">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4"><path d="M6.3 2.84A1.5 1.5 0 0 0 4 4.11v11.78a1.5 1.5 0 0 0 2.3 1.27l9.344-5.891a1.5 1.5 0 0 0 0-2.538L6.3 2.84Z" /></svg>
                    {{ __('landing.hero_cta_secondary') }}
                </a>
            </div>
        </div>

        {{-- ─────── Dashboard mockup ─────── --}}
        <div class="relative mx-auto mt-16 max-w-5xl sm:mt-20" aria-hidden="true">
            {{-- Top floating annotation pills --}}
            <div class="absolute -left-2 top-6 hidden rotate-[-3deg] lg:block">
                <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-900 px-3 py-1.5 text-xs font-semibold text-white shadow-lg">
                    <span class="h-1.5 w-1.5 rounded-full bg-teal-400"></span>
                    {{ __('landing.showcase_pill_1') }}
                </span>
            </div>
            <div class="absolute -right-4 top-24 hidden rotate-[4deg] lg:block">
                <span class="inline-flex items-center gap-1.5 rounded-full bg-teal-600 px-3 py-1.5 text-xs font-semibold text-white shadow-lg">
                    <span class="h-1.5 w-1.5 rounded-full bg-white"></span>
                    {{ __('landing.showcase_pill_2') }}
                </span>
            </div>

            {{-- Mockup frame --}}
            <div class="rounded-2xl bg-gradient-to-b from-slate-900 to-slate-800 p-2.5 shadow-2xl ring-1 ring-slate-900/10 sm:p-3">
                {{-- Window chrome --}}
                <div class="flex items-center gap-1.5 px-2.5 py-2">
                    <span class="h-2.5 w-2.5 rounded-full bg-red-400/80"></span>
                    <span class="h-2.5 w-2.5 rounded-full bg-amber-400/80"></span>
                    <span class="h-2.5 w-2.5 rounded-full bg-emerald-400/80"></span>
                    <span class="ml-3 hidden text-[11px] font-medium text-slate-400 sm:inline">pms.bjptechnologies.co.tz / {{ __('landing.mockup_title') }}</span>
                </div>

                {{-- Mockup body --}}
                <div class="grid grid-cols-12 overflow-hidden rounded-lg bg-white">
                    {{-- Sidebar --}}
                    <aside class="col-span-3 hidden border-r border-slate-100 bg-slate-50/60 p-4 sm:block lg:col-span-2">
                        <div class="mb-4 flex items-center gap-2">
                            <span class="inline-flex h-7 w-7 items-center justify-center rounded-md bg-teal-600 text-[10px] font-extrabold text-white">P</span>
                            <span class="text-[11px] font-bold tracking-wide text-slate-900">PMS</span>
                        </div>
                        <div class="space-y-1.5">
                            <div class="flex items-center gap-2 rounded-md bg-teal-600/10 px-2 py-1.5 text-[10px] font-semibold text-teal-700">
                                <span class="h-1.5 w-1.5 rounded-full bg-teal-600"></span> Dashboard
                            </div>
                            <div class="flex items-center gap-2 px-2 py-1.5 text-[10px] font-medium text-slate-500"><span class="h-1.5 w-1.5 rounded-full bg-slate-300"></span> Properties</div>
                            <div class="flex items-center gap-2 px-2 py-1.5 text-[10px] font-medium text-slate-500"><span class="h-1.5 w-1.5 rounded-full bg-slate-300"></span> Renters</div>
                            <div class="flex items-center gap-2 px-2 py-1.5 text-[10px] font-medium text-slate-500"><span class="h-1.5 w-1.5 rounded-full bg-slate-300"></span> Invoices</div>
                            <div class="flex items-center gap-2 px-2 py-1.5 text-[10px] font-medium text-slate-500"><span class="h-1.5 w-1.5 rounded-full bg-slate-300"></span> Maintenance</div>
                            <div class="flex items-center gap-2 px-2 py-1.5 text-[10px] font-medium text-slate-500"><span class="h-1.5 w-1.5 rounded-full bg-slate-300"></span> Reports</div>
                        </div>
                    </aside>

                    {{-- Main area --}}
                    <main class="col-span-12 p-4 sm:col-span-9 sm:p-5 lg:col-span-10">
                        {{-- Topbar --}}
                        <div class="mb-4 flex items-center justify-between">
                            <div>
                                <div class="text-[10px] font-medium uppercase tracking-wider text-slate-400">Workspace</div>
                                <div class="text-sm font-bold text-slate-900">{{ __('landing.mockup_title') }}</div>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="inline-flex rounded-md border border-slate-200 px-2 py-1 text-[10px] font-semibold text-slate-600">EN / SW</span>
                                <span class="h-6 w-6 rounded-full bg-gradient-to-br from-teal-400 to-teal-600"></span>
                            </div>
                        </div>

                        {{-- Stat cards --}}
                        <div class="grid grid-cols-3 gap-3">
                            <div class="rounded-lg border border-slate-200/70 bg-white p-3">
                                <div class="text-[9px] font-semibold uppercase tracking-wider text-slate-500">{{ __('landing.mockup_stat_collected') }}</div>
                                <div class="mt-1 text-sm font-extrabold text-slate-900 sm:text-base">TSh 24.5M</div>
                                <div class="mt-1 text-[10px] font-semibold text-teal-600">▲ 12.4%</div>
                            </div>
                            <div class="rounded-lg border border-slate-200/70 bg-white p-3">
                                <div class="text-[9px] font-semibold uppercase tracking-wider text-slate-500">{{ __('landing.mockup_stat_outstanding') }}</div>
                                <div class="mt-1 text-sm font-extrabold text-slate-900 sm:text-base">TSh 3.2M</div>
                                <div class="mt-1 text-[10px] font-semibold text-amber-600">8 invoices</div>
                            </div>
                            <div class="rounded-lg border border-slate-200/70 bg-white p-3">
                                <div class="text-[9px] font-semibold uppercase tracking-wider text-slate-500">{{ __('landing.mockup_stat_units') }}</div>
                                <div class="mt-1 text-sm font-extrabold text-slate-900 sm:text-base">128 / 134</div>
                                <div class="mt-1 text-[10px] font-semibold text-slate-500">96% occupied</div>
                            </div>
                        </div>

                        {{-- Chart --}}
                        <div class="mt-4 rounded-lg border border-slate-200/70 bg-white p-3">
                            <div class="mb-3 flex items-center justify-between">
                                <div class="text-[10px] font-semibold uppercase tracking-wider text-slate-500">{{ __('landing.mockup_chart_label') }}</div>
                                <div class="text-[10px] font-medium text-slate-400">2026</div>
                            </div>
                            <div class="flex h-20 items-end justify-between gap-2 sm:h-24">
                                @php $bars = [55, 70, 60, 82, 68, 92]; @endphp
                                @foreach ($bars as $h)
                                    <div class="flex-1 rounded-t bg-gradient-to-t from-teal-600 to-teal-400" style="height: {{ $h }}%"></div>
                                @endforeach
                            </div>
                            <div class="mt-2 flex justify-between text-[9px] font-medium text-slate-400">
                                <span>Dec</span><span>Jan</span><span>Feb</span><span>Mar</span><span>Apr</span><span>May</span>
                            </div>
                        </div>

                        {{-- Recent activity --}}
                        <div class="mt-3 hidden rounded-lg border border-slate-200/70 bg-white p-3 sm:block">
                            <div class="mb-2 text-[10px] font-semibold uppercase tracking-wider text-slate-500">{{ __('landing.mockup_recent') }}</div>
                            <div class="space-y-1.5 text-[11px]">
                                <div class="flex items-center gap-2"><span class="h-1.5 w-1.5 rounded-full bg-teal-500"></span><span class="text-slate-700">{{ __('landing.mockup_activity_1') }}</span></div>
                                <div class="flex items-center gap-2"><span class="h-1.5 w-1.5 rounded-full bg-sky-500"></span><span class="text-slate-700">{{ __('landing.mockup_activity_2') }}</span></div>
                                <div class="flex items-center gap-2"><span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span><span class="text-slate-700">{{ __('landing.mockup_activity_3') }}</span></div>
                            </div>
                        </div>
                    </main>
                </div>
            </div>

            {{-- Bottom floating annotation --}}
            <div class="absolute -bottom-3 left-1/2 hidden -translate-x-1/2 rotate-[1deg] lg:block">
                <span class="inline-flex items-center gap-1.5 rounded-full bg-white px-3 py-1.5 text-xs font-semibold text-slate-900 shadow-lg ring-1 ring-slate-200">
                    <span class="h-1.5 w-1.5 rounded-full bg-amber-400"></span>
                    {{ __('landing.showcase_pill_4') }}
                </span>
            </div>
        </div>
    </div>
</section>

{{-- ═══════════════════════════════════════════════════════════ TRUST BAR ══ --}}
<section class="border-y border-slate-100 bg-slate-50/50">
    <div class="mx-auto max-w-7xl px-5 py-10 sm:px-6 sm:py-12 lg:px-8">
        <p class="mb-6 text-center text-xs font-semibold uppercase tracking-widest text-slate-500">{{ __('landing.trust_eyebrow') }}</p>
        <div class="grid grid-cols-2 gap-x-6 gap-y-8 md:grid-cols-4">
            @foreach (['1', '2', '3', '4'] as $i)
                <div class="text-center">
                    <div class="text-2xl font-extrabold text-slate-900 sm:text-3xl">{{ __('landing.trust_stat_'.$i.'_value') }}</div>
                    <div class="mt-1 text-xs font-medium text-slate-500 sm:text-sm">{{ __('landing.trust_stat_'.$i.'_label') }}</div>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ═══════════════════════════════════════════════════════ PROBLEM / SOLUTION ══ --}}
<section class="bg-white py-20 sm:py-24">
    <div class="mx-auto max-w-7xl px-5 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-2xl text-center">
            <h2 class="text-3xl font-extrabold tracking-tight text-slate-900 sm:text-4xl lg:text-5xl">
                {{ __('landing.problem_title') }}
            </h2>
            <p class="mt-4 text-lg text-slate-600">{{ __('landing.problem_subtitle') }}</p>
        </div>

        <div class="mx-auto mt-14 grid max-w-5xl gap-6 md:grid-cols-2">
            {{-- Without PMS --}}
            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-6 sm:p-8">
                <div class="mb-5 inline-flex items-center gap-2 rounded-full bg-rose-100 px-3 py-1 text-xs font-bold uppercase tracking-wider text-rose-700">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-3.5 w-3.5"><path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm3.78-9.22a.75.75 0 0 0-1.06-1.06L10 10.44 7.28 7.72a.75.75 0 0 0-1.06 1.06L8.94 11.5l-2.72 2.72a.75.75 0 1 0 1.06 1.06L10 12.56l2.72 2.72a.75.75 0 1 0 1.06-1.06L11.06 11.5l2.72-2.72Z" clip-rule="evenodd" /></svg>
                    {{ __('landing.problem_label') }}
                </div>
                <ul class="space-y-4">
                    @foreach (['1', '2', '3', '4'] as $i)
                        <li class="flex gap-3 text-sm leading-6 text-slate-600">
                            <span class="mt-1.5 inline-block h-1.5 w-1.5 flex-none rounded-full bg-rose-400"></span>
                            <span>{{ __('landing.problem_'.$i) }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>

            {{-- With PMS --}}
            <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-teal-600 to-teal-800 p-6 text-white shadow-xl shadow-teal-700/20 sm:p-8">
                <div class="absolute -right-6 -top-6 h-32 w-32 rounded-full bg-white/10 blur-2xl"></div>
                <div class="relative">
                    <div class="mb-5 inline-flex items-center gap-2 rounded-full bg-white/15 px-3 py-1 text-xs font-bold uppercase tracking-wider text-white ring-1 ring-inset ring-white/20">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-3.5 w-3.5"><path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm3.857-9.809a.75.75 0 0 0-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 1 0-1.06 1.061l2.5 2.5a.75.75 0 0 0 1.137-.089l4-5.5Z" clip-rule="evenodd" /></svg>
                        {{ __('landing.solution_label') }}
                    </div>
                    <ul class="space-y-4">
                        @foreach (['1', '2', '3', '4'] as $i)
                            <li class="flex gap-3 text-sm leading-6 text-teal-50">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="mt-0.5 h-5 w-5 flex-none text-teal-200"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd" /></svg>
                                <span>{{ __('landing.solution_'.$i) }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ═════════════════════════════════════════════════════════════ FEATURES ══ --}}
<section id="features" class="bg-slate-50 py-20 sm:py-24">
    <div class="mx-auto max-w-7xl px-5 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-2xl text-center">
            <p class="text-xs font-semibold uppercase tracking-widest text-teal-700">{{ __('landing.features_eyebrow') }}</p>
            <h2 class="mt-3 text-3xl font-extrabold tracking-tight text-slate-900 sm:text-4xl lg:text-5xl">
                {{ __('landing.features_title') }}
            </h2>
            <p class="mt-4 text-lg text-slate-600">
                {{ __('landing.features_subtitle') }}
            </p>
        </div>

        <div class="mx-auto mt-16 grid max-w-2xl grid-cols-1 gap-6 md:max-w-none md:grid-cols-2 lg:grid-cols-3">
            @php
                $features = [
                    ['n' => '1', 'svg' => '<path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Z" />'],
                    ['n' => '2', 'svg' => '<path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />'],
                    ['n' => '3', 'svg' => '<path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />'],
                    ['n' => '4', 'svg' => '<path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17 17.25 21A2.652 2.652 0 0 0 21 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 1 1-3.586-3.586l6.837-5.63m5.108-.233c.55-.164 1.163-.188 1.743-.14a4.5 4.5 0 0 0 4.486-6.336l-3.276 3.277a3.004 3.004 0 0 1-2.25-2.25l3.276-3.276a4.5 4.5 0 0 0-6.336 4.486c.091 1.076-.071 2.264-.904 2.95l-.102.085m-1.745 1.437L5.909 7.5H4.5L2.25 3.75l1.5-1.5L7.5 4.5v1.409l4.26 4.26m-1.745 1.437 1.745-1.437m6.615 8.206L15.75 15.75M4.867 19.125h.008v.008h-.008v-.008Z" />'],
                    ['n' => '5', 'svg' => '<path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" />'],
                    ['n' => '6', 'svg' => '<path stroke-linecap="round" stroke-linejoin="round" d="m10.5 21 5.25-11.25L21 21m-9-3h7.5M3 5.621a48.474 48.474 0 0 1 6-.371m0 0c1.12 0 2.233.038 3.334.114M9 5.25V3m3.334 2.364C11.176 10.658 7.69 15.08 3 17.502m9.334-12.138c.896.061 1.785.147 2.666.257m-4.589 8.495a18.023 18.023 0 0 1-3.827-5.802" />'],
                ];
            @endphp
            @foreach ($features as $f)
                <div class="group cursor-pointer rounded-2xl bg-white p-7 shadow-sm ring-1 ring-slate-200/70 transition-all duration-200 hover:-translate-y-1 hover:shadow-xl hover:shadow-teal-600/10 hover:ring-teal-300">
                    <div class="inline-flex h-12 w-12 items-center justify-center rounded-xl bg-gradient-to-br from-teal-50 to-teal-100 text-teal-700 ring-1 ring-teal-200/60 transition-colors group-hover:from-teal-600 group-hover:to-teal-700 group-hover:text-white group-hover:ring-teal-700/20">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-6 w-6">{!! $f['svg'] !!}</svg>
                    </div>
                    <h3 class="mt-5 text-lg font-bold text-slate-900">{{ __('landing.feature_'.$f['n'].'_title') }}</h3>
                    <p class="mt-2 text-sm leading-6 text-slate-600">{{ __('landing.feature_'.$f['n'].'_desc') }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ════════════════════════════════════════════════════════ HOW IT WORKS ══ --}}
<section id="how" class="bg-white py-20 sm:py-24">
    <div class="mx-auto max-w-7xl px-5 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-2xl text-center">
            <p class="text-xs font-semibold uppercase tracking-widest text-teal-700">{{ __('landing.how_eyebrow') }}</p>
            <h2 class="mt-3 text-3xl font-extrabold tracking-tight text-slate-900 sm:text-4xl lg:text-5xl">
                {{ __('landing.how_title') }}
            </h2>
            <p class="mt-4 text-lg text-slate-600">{{ __('landing.how_subtitle') }}</p>
        </div>

        <div class="relative mx-auto mt-16 max-w-5xl">
            {{-- Connecting line on desktop --}}
            <div class="absolute left-0 right-0 top-6 hidden h-px bg-gradient-to-r from-transparent via-teal-300 to-transparent md:block"></div>

            <div class="relative grid grid-cols-1 gap-12 md:grid-cols-3">
                @foreach (['1', '2', '3'] as $i)
                    <div class="text-center">
                        <div class="relative mx-auto inline-flex h-12 w-12 items-center justify-center rounded-full bg-gradient-to-br from-teal-500 to-teal-700 text-lg font-extrabold text-white shadow-lg shadow-teal-600/30 ring-4 ring-white">
                            {{ $i }}
                        </div>
                        <h3 class="mt-5 text-lg font-bold text-slate-900">{{ __('landing.how_step_'.$i.'_title') }}</h3>
                        <p class="mt-2 text-sm leading-6 text-slate-600">{{ __('landing.how_step_'.$i.'_desc') }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</section>

{{-- ══════════════════════════════════════════════════════════ SHOWCASE ══ --}}
<section id="showcase" class="bg-gradient-to-b from-white to-slate-50 py-20 sm:py-24">
    <div class="mx-auto max-w-7xl px-5 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-2xl text-center">
            <p class="text-xs font-semibold uppercase tracking-widest text-teal-700">{{ __('landing.showcase_eyebrow') }}</p>
            <h2 class="mt-3 text-3xl font-extrabold tracking-tight text-slate-900 sm:text-4xl lg:text-5xl">
                {{ __('landing.showcase_title') }}
            </h2>
            <p class="mt-4 text-lg text-slate-600">{{ __('landing.showcase_subtitle') }}</p>
        </div>

        <div class="mx-auto mt-14 grid max-w-6xl items-center gap-12 lg:grid-cols-5">
            {{-- Annotated feature list --}}
            <ul class="space-y-5 lg:col-span-2">
                @php
                    $pills = [
                        ['1', 'M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z'],
                        ['2', 'm10.5 21 5.25-11.25L21 21m-9-3h7.5M3 5.621a48.474 48.474 0 0 1 6-.371m0 0c1.12 0 2.233.038 3.334.114'],
                        ['3', 'M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25'],
                        ['4', 'M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625Z'],
                    ];
                @endphp
                @foreach ($pills as [$n, $path])
                    <li class="flex items-start gap-4">
                        <div class="flex h-10 w-10 flex-none items-center justify-center rounded-xl bg-teal-600/10 text-teal-700 ring-1 ring-teal-200">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-5 w-5"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $path }}" /></svg>
                        </div>
                        <div>
                            <div class="text-base font-semibold text-slate-900">{{ __('landing.showcase_pill_'.$n) }}</div>
                        </div>
                    </li>
                @endforeach
            </ul>

            {{-- Larger annotated mockup --}}
            <div class="lg:col-span-3">
                <div class="overflow-hidden rounded-2xl bg-gradient-to-b from-slate-900 to-slate-800 p-2.5 shadow-2xl ring-1 ring-slate-900/10">
                    <div class="flex items-center gap-1.5 px-2.5 py-2">
                        <span class="h-2.5 w-2.5 rounded-full bg-red-400/80"></span>
                        <span class="h-2.5 w-2.5 rounded-full bg-amber-400/80"></span>
                        <span class="h-2.5 w-2.5 rounded-full bg-emerald-400/80"></span>
                    </div>
                    <div class="rounded-lg bg-white p-5">
                        <div class="mb-4 flex items-center justify-between">
                            <div>
                                <div class="text-xs font-semibold uppercase tracking-wider text-slate-400">Invoice</div>
                                <div class="text-base font-bold text-slate-900">INV-2026-0042</div>
                            </div>
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-bold text-emerald-700 ring-1 ring-emerald-200">
                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span> PAID
                            </span>
                        </div>
                        <div class="grid grid-cols-2 gap-4 border-t border-slate-100 pt-4">
                            <div>
                                <div class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Renter</div>
                                <div class="mt-0.5 text-sm font-semibold text-slate-900">Asha Mwakasege</div>
                                <div class="text-xs text-slate-500">+255 712 345 678</div>
                            </div>
                            <div>
                                <div class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Unit</div>
                                <div class="mt-0.5 text-sm font-semibold text-slate-900">Block A · Unit 3B</div>
                                <div class="text-xs text-slate-500">Mikocheni B</div>
                            </div>
                        </div>
                        <div class="mt-4 space-y-1.5 border-t border-slate-100 pt-4 text-sm">
                            <div class="flex justify-between text-slate-600"><span>Monthly rent</span><span class="font-semibold text-slate-900">TSh 450,000</span></div>
                            <div class="flex justify-between text-slate-600"><span>Service charge</span><span class="font-semibold text-slate-900">TSh 35,000</span></div>
                            <div class="flex justify-between border-t border-slate-100 pt-2 text-base font-bold text-slate-900"><span>Total</span><span class="text-teal-700">TSh 485,000</span></div>
                        </div>
                        <div class="mt-4 flex items-center gap-2 border-t border-slate-100 pt-3 text-xs text-slate-500">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-3.5 w-3.5 text-teal-600"><path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm3.857-9.809a.75.75 0 0 0-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 1 0-1.06 1.061l2.5 2.5a.75.75 0 0 0 1.137-.089l4-5.5Z" clip-rule="evenodd" /></svg>
                            Paid via M-Pesa · 01/06/2026 14:32
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ════════════════════════════════════════════════════════ PRINCIPLES ══ --}}
<section class="bg-white py-20 sm:py-24">
    <div class="mx-auto max-w-7xl px-5 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-2xl text-center">
            <p class="text-xs font-semibold uppercase tracking-widest text-teal-700">{{ __('landing.principles_eyebrow') }}</p>
            <h2 class="mt-3 text-3xl font-extrabold tracking-tight text-slate-900 sm:text-4xl lg:text-5xl">
                {{ __('landing.principles_title') }}
            </h2>
            <p class="mt-4 text-lg text-slate-600">{{ __('landing.principles_subtitle') }}</p>
        </div>

        <div class="mx-auto mt-14 grid max-w-5xl gap-6 md:grid-cols-3">
            @foreach (['1', '2', '3'] as $i)
                <div class="rounded-2xl border border-slate-200 bg-slate-50/50 p-7 transition-colors hover:border-teal-300 hover:bg-white">
                    <div class="text-3xl font-extrabold text-teal-700">0{{ $i }}</div>
                    <h3 class="mt-3 text-lg font-bold text-slate-900">{{ __('landing.principle_'.$i.'_title') }}</h3>
                    <p class="mt-2 text-sm leading-6 text-slate-600">{{ __('landing.principle_'.$i.'_desc') }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ════════════════════════════════════════════════════════════════ FAQ ══ --}}
<section id="faq" class="bg-slate-50 py-20 sm:py-24">
    <div class="mx-auto max-w-3xl px-5 sm:px-6 lg:px-8">
        <div class="text-center">
            <p class="text-xs font-semibold uppercase tracking-widest text-teal-700">{{ __('landing.faq_eyebrow') }}</p>
            <h2 class="mt-3 text-3xl font-extrabold tracking-tight text-slate-900 sm:text-4xl lg:text-5xl">
                {{ __('landing.faq_title') }}
            </h2>
            <p class="mt-4 text-lg text-slate-600">{{ __('landing.faq_subtitle') }}</p>
        </div>

        <div class="mt-12 divide-y divide-slate-200 rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
            @foreach (['1', '2', '3', '4', '5', '6'] as $i)
                <details class="group">
                    <summary class="flex cursor-pointer list-none items-start justify-between gap-4 p-5 sm:p-6">
                        <span class="text-base font-semibold text-slate-900 sm:text-lg">{{ __('landing.faq_'.$i.'_q') }}</span>
                        <span class="flex h-7 w-7 flex-none items-center justify-center rounded-full bg-slate-100 text-slate-600 transition-transform duration-200 group-open:rotate-45 group-open:bg-teal-100 group-open:text-teal-700">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4"><path fill-rule="evenodd" d="M10 5a.75.75 0 0 1 .75.75v3.5h3.5a.75.75 0 0 1 0 1.5h-3.5v3.5a.75.75 0 0 1-1.5 0v-3.5h-3.5a.75.75 0 0 1 0-1.5h3.5v-3.5A.75.75 0 0 1 10 5Z" clip-rule="evenodd" /></svg>
                        </span>
                    </summary>
                    <p class="px-5 pb-5 text-sm leading-7 text-slate-600 sm:px-6 sm:pb-6 sm:text-base">{{ __('landing.faq_'.$i.'_a') }}</p>
                </details>
            @endforeach
        </div>
    </div>
</section>

{{-- ════════════════════════════════════════════════════════════ FINAL CTA ══ --}}
<section class="relative overflow-hidden bg-slate-900 py-20 sm:py-24">
    <div class="absolute inset-0 -z-0 pms-grid-bg opacity-30"></div>
    <div class="absolute -left-20 top-0 -z-0 h-72 w-72 rounded-full bg-teal-500/30 blur-3xl"></div>
    <div class="absolute -right-20 bottom-0 -z-0 h-72 w-72 rounded-full bg-teal-700/30 blur-3xl"></div>

    <div class="relative mx-auto max-w-4xl px-5 text-center sm:px-6 lg:px-8">
        <p class="text-xs font-semibold uppercase tracking-widest text-teal-300">{{ __('landing.final_cta_eyebrow') }}</p>
        <h2 class="mt-3 text-3xl font-extrabold tracking-tight text-white sm:text-4xl lg:text-5xl">
            {{ __('landing.final_cta_title') }}
        </h2>
        <p class="mx-auto mt-4 max-w-2xl text-lg leading-8 text-slate-300">
            {{ __('landing.final_cta_subtitle') }}
        </p>
        <a href="mailto:{{ __('landing.footer_email') }}?subject=PMS%20demo%20request" class="mt-10 inline-flex cursor-pointer items-center gap-2 rounded-full bg-white px-7 py-3.5 text-base font-semibold text-slate-900 shadow-xl ring-1 ring-white/10 transition-colors hover:bg-teal-50">
            {{ __('landing.final_cta_button') }}
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4"><path fill-rule="evenodd" d="M3 10a.75.75 0 0 1 .75-.75h10.638L10.23 5.29a.75.75 0 1 1 1.04-1.08l5.5 5.25a.75.75 0 0 1 0 1.08l-5.5 5.25a.75.75 0 1 1-1.04-1.08l4.158-3.96H3.75A.75.75 0 0 1 3 10Z" clip-rule="evenodd" /></svg>
        </a>
        <p class="mt-4 text-xs text-slate-400">{{ __('landing.final_cta_note') }}</p>
    </div>
</section>

{{-- ═════════════════════════════════════════════════════════════════ FOOTER ══ --}}
<footer id="contact" class="border-t border-slate-200 bg-white">
    <div class="mx-auto max-w-7xl px-5 py-14 sm:px-6 lg:px-8">
        <div class="grid grid-cols-2 gap-8 md:grid-cols-4">
            {{-- Brand --}}
            <div class="col-span-2 md:col-span-1">
                <a href="/" class="inline-flex items-center gap-2.5 text-base font-bold text-slate-900">
                    <span class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-teal-600 to-teal-700 text-sm font-extrabold text-white shadow-sm">P</span>
                    <span>{{ __('landing.brand_name') }}</span>
                </a>
                <p class="mt-3 max-w-xs text-sm text-slate-600">{{ __('landing.footer_tagline') }}</p>
            </div>

            <div>
                <h4 class="text-xs font-bold uppercase tracking-widest text-slate-500">{{ __('landing.footer_col_product') }}</h4>
                <ul class="mt-4 space-y-2.5 text-sm text-slate-600">
                    <li><a href="#features" class="transition-colors hover:text-slate-900">{{ __('landing.footer_link_features') }}</a></li>
                    <li><a href="#how" class="transition-colors hover:text-slate-900">{{ __('landing.footer_link_how') }}</a></li>
                    <li><a href="#faq" class="transition-colors hover:text-slate-900">{{ __('landing.footer_link_faq') }}</a></li>
                    <li><a href="/admin/login" class="transition-colors hover:text-slate-900">{{ __('landing.footer_link_sign_in') }}</a></li>
                </ul>
            </div>

            <div>
                <h4 class="text-xs font-bold uppercase tracking-widest text-slate-500">{{ __('landing.footer_col_company') }}</h4>
                <ul class="mt-4 space-y-2.5 text-sm text-slate-600">
                    <li><a href="https://bjptechnologies.co.tz" target="_blank" rel="noopener" class="transition-colors hover:text-slate-900">{{ __('landing.footer_link_about') }}</a></li>
                    <li><a href="mailto:{{ __('landing.footer_email') }}?subject=PMS%20demo%20request" class="transition-colors hover:text-slate-900">{{ __('landing.footer_link_demo') }}</a></li>
                </ul>
            </div>

            <div>
                <h4 class="text-xs font-bold uppercase tracking-widest text-slate-500">{{ __('landing.footer_col_contact') }}</h4>
                <ul class="mt-4 space-y-2.5 text-sm text-slate-600">
                    <li><a href="mailto:{{ __('landing.footer_email') }}" class="transition-colors hover:text-slate-900">{{ __('landing.footer_email') }}</a></li>
                    <li>{{ __('landing.footer_location') }}</li>
                </ul>
            </div>
        </div>

        <div class="mt-12 flex flex-col items-center justify-between gap-4 border-t border-slate-200 pt-8 text-xs text-slate-500 sm:flex-row">
            <p>&copy; {{ date('Y') }} {{ __('landing.footer_company') }} {{ __('landing.footer_rights') }}</p>
            @if (Route::has('locale.switch'))
                <form method="POST" action="{{ route('locale.switch') }}" class="flex items-center gap-1.5 text-xs">
                    @csrf
                    <span class="font-medium text-slate-500">{{ __('landing.lang_label') }}:</span>
                    <button type="submit" name="locale" value="en" class="cursor-pointer font-semibold transition-colors {{ app()->getLocale() === 'en' ? 'text-slate-900' : 'text-slate-400 hover:text-slate-700' }}">{{ __('landing.lang_en') }}</button>
                    <span class="text-slate-300">·</span>
                    <button type="submit" name="locale" value="sw" class="cursor-pointer font-semibold transition-colors {{ app()->getLocale() === 'sw' ? 'text-slate-900' : 'text-slate-400 hover:text-slate-700' }}">{{ __('landing.lang_sw') }}</button>
                </form>
            @endif
        </div>
    </div>
</footer>

</body>
</html>
