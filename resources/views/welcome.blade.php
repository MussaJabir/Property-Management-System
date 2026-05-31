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
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-white text-slate-900 antialiased selection:bg-emerald-200">

{{-- Navigation --}}
<header class="sticky top-0 z-50 border-b border-slate-100 bg-white/80 backdrop-blur">
    <div class="mx-auto flex max-w-7xl items-center justify-between px-6 py-4 lg:px-8">
        <a href="/" class="flex items-center gap-2 text-lg font-bold text-slate-900">
            <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-emerald-600 text-sm font-bold text-white shadow-sm">P</span>
            <span>{{ __('landing.brand_name') }}</span>
        </a>

        <nav class="hidden items-center gap-8 text-sm font-medium text-slate-600 md:flex">
            <a href="#features" class="hover:text-slate-900">{{ __('landing.nav_features') }}</a>
            <a href="#how" class="hover:text-slate-900">{{ __('landing.nav_how') }}</a>
            <a href="#contact" class="hover:text-slate-900">{{ __('landing.nav_contact') }}</a>
        </nav>

        <div class="flex items-center gap-3">
            <a href="/admin/login" class="hidden text-sm font-medium text-slate-700 hover:text-slate-900 sm:inline-flex">
                {{ __('landing.cta_sign_in') }} <span aria-hidden="true">&rarr;</span>
            </a>
            <a href="mailto:info@bjptechnologies.co.tz?subject=PMS%20demo%20request" class="inline-flex items-center rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-700">
                {{ __('landing.cta_demo') }}
            </a>
        </div>
    </div>
</header>

{{-- Hero --}}
<section class="relative overflow-hidden">
    <div class="absolute inset-0 -z-10 bg-gradient-to-b from-emerald-50/60 via-white to-white"></div>
    <div class="mx-auto max-w-7xl px-6 pt-20 pb-24 lg:px-8 lg:pt-28 lg:pb-32">
        <div class="mx-auto max-w-3xl text-center">
            <span class="inline-flex items-center rounded-full bg-emerald-50 px-3 py-1 text-xs font-medium text-emerald-700 ring-1 ring-inset ring-emerald-200">
                {{ __('landing.hero_eyebrow') }}
            </span>
            <h1 class="mt-6 text-4xl font-bold tracking-tight text-slate-900 sm:text-5xl lg:text-6xl">
                {{ __('landing.hero_title') }}
            </h1>
            <p class="mx-auto mt-6 max-w-2xl text-lg leading-8 text-slate-600">
                {{ __('landing.hero_subtitle') }}
            </p>
            <div class="mt-10 flex flex-wrap items-center justify-center gap-4">
                <a href="mailto:info@bjptechnologies.co.tz?subject=PMS%20demo%20request" class="inline-flex items-center rounded-md bg-emerald-600 px-5 py-3 text-base font-semibold text-white shadow-sm transition hover:bg-emerald-500">
                    {{ __('landing.cta_demo') }}
                </a>
                <a href="#features" class="text-base font-semibold text-slate-700 hover:text-slate-900">
                    {{ __('landing.cta_learn_more') }} <span aria-hidden="true">&rarr;</span>
                </a>
            </div>
        </div>
    </div>
</section>

{{-- Features --}}
<section id="features" class="bg-slate-50 py-24">
    <div class="mx-auto max-w-7xl px-6 lg:px-8">
        <div class="mx-auto max-w-2xl text-center">
            <h2 class="text-3xl font-bold tracking-tight text-slate-900 sm:text-4xl">
                {{ __('landing.features_title') }}
            </h2>
            <p class="mt-4 text-lg text-slate-600">
                {{ __('landing.features_subtitle') }}
            </p>
        </div>

        <div class="mx-auto mt-16 grid max-w-2xl grid-cols-1 gap-6 md:max-w-none md:grid-cols-2 lg:grid-cols-3">
            <div class="rounded-2xl bg-white p-8 shadow-sm ring-1 ring-slate-200 transition hover:shadow-md">
                <div class="inline-flex h-11 w-11 items-center justify-center rounded-lg bg-emerald-600/10 text-emerald-700">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-6 w-6"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Z" /></svg>
                </div>
                <h3 class="mt-5 text-lg font-semibold text-slate-900">{{ __('landing.feature_1_title') }}</h3>
                <p class="mt-2 text-sm leading-6 text-slate-600">{{ __('landing.feature_1_desc') }}</p>
            </div>

            <div class="rounded-2xl bg-white p-8 shadow-sm ring-1 ring-slate-200 transition hover:shadow-md">
                <div class="inline-flex h-11 w-11 items-center justify-center rounded-lg bg-emerald-600/10 text-emerald-700">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-6 w-6"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" /></svg>
                </div>
                <h3 class="mt-5 text-lg font-semibold text-slate-900">{{ __('landing.feature_2_title') }}</h3>
                <p class="mt-2 text-sm leading-6 text-slate-600">{{ __('landing.feature_2_desc') }}</p>
            </div>

            <div class="rounded-2xl bg-white p-8 shadow-sm ring-1 ring-slate-200 transition hover:shadow-md">
                <div class="inline-flex h-11 w-11 items-center justify-center rounded-lg bg-emerald-600/10 text-emerald-700">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-6 w-6"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" /></svg>
                </div>
                <h3 class="mt-5 text-lg font-semibold text-slate-900">{{ __('landing.feature_3_title') }}</h3>
                <p class="mt-2 text-sm leading-6 text-slate-600">{{ __('landing.feature_3_desc') }}</p>
            </div>

            <div class="rounded-2xl bg-white p-8 shadow-sm ring-1 ring-slate-200 transition hover:shadow-md">
                <div class="inline-flex h-11 w-11 items-center justify-center rounded-lg bg-emerald-600/10 text-emerald-700">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-6 w-6"><path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17 17.25 21A2.652 2.652 0 0 0 21 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 1 1-3.586-3.586l6.837-5.63m5.108-.233c.55-.164 1.163-.188 1.743-.14a4.5 4.5 0 0 0 4.486-6.336l-3.276 3.277a3.004 3.004 0 0 1-2.25-2.25l3.276-3.276a4.5 4.5 0 0 0-6.336 4.486c.091 1.076-.071 2.264-.904 2.95l-.102.085m-1.745 1.437L5.909 7.5H4.5L2.25 3.75l1.5-1.5L7.5 4.5v1.409l4.26 4.26m-1.745 1.437 1.745-1.437m6.615 8.206L15.75 15.75M4.867 19.125h.008v.008h-.008v-.008Z" /></svg>
                </div>
                <h3 class="mt-5 text-lg font-semibold text-slate-900">{{ __('landing.feature_4_title') }}</h3>
                <p class="mt-2 text-sm leading-6 text-slate-600">{{ __('landing.feature_4_desc') }}</p>
            </div>

            <div class="rounded-2xl bg-white p-8 shadow-sm ring-1 ring-slate-200 transition hover:shadow-md">
                <div class="inline-flex h-11 w-11 items-center justify-center rounded-lg bg-emerald-600/10 text-emerald-700">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-6 w-6"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" /></svg>
                </div>
                <h3 class="mt-5 text-lg font-semibold text-slate-900">{{ __('landing.feature_5_title') }}</h3>
                <p class="mt-2 text-sm leading-6 text-slate-600">{{ __('landing.feature_5_desc') }}</p>
            </div>

            <div class="rounded-2xl bg-white p-8 shadow-sm ring-1 ring-slate-200 transition hover:shadow-md">
                <div class="inline-flex h-11 w-11 items-center justify-center rounded-lg bg-emerald-600/10 text-emerald-700">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-6 w-6"><path stroke-linecap="round" stroke-linejoin="round" d="m10.5 21 5.25-11.25L21 21m-9-3h7.5M3 5.621a48.474 48.474 0 0 1 6-.371m0 0c1.12 0 2.233.038 3.334.114M9 5.25V3m3.334 2.364C11.176 10.658 7.69 15.08 3 17.502m9.334-12.138c.896.061 1.785.147 2.666.257m-4.589 8.495a18.023 18.023 0 0 1-3.827-5.802" /></svg>
                </div>
                <h3 class="mt-5 text-lg font-semibold text-slate-900">{{ __('landing.feature_6_title') }}</h3>
                <p class="mt-2 text-sm leading-6 text-slate-600">{{ __('landing.feature_6_desc') }}</p>
            </div>
        </div>
    </div>
</section>

{{-- How it works --}}
<section id="how" class="bg-white py-24">
    <div class="mx-auto max-w-7xl px-6 lg:px-8">
        <div class="mx-auto max-w-2xl text-center">
            <h2 class="text-3xl font-bold tracking-tight text-slate-900 sm:text-4xl">
                {{ __('landing.how_title') }}
            </h2>
            <p class="mt-4 text-lg text-slate-600">{{ __('landing.how_subtitle') }}</p>
        </div>

        <div class="mx-auto mt-16 grid max-w-4xl grid-cols-1 gap-x-12 gap-y-10 md:grid-cols-3">
            <div class="text-center">
                <div class="mx-auto inline-flex h-12 w-12 items-center justify-center rounded-full bg-emerald-600 text-lg font-bold text-white shadow-sm">1</div>
                <h3 class="mt-5 text-lg font-semibold text-slate-900">{{ __('landing.how_step_1_title') }}</h3>
                <p class="mt-2 text-sm leading-6 text-slate-600">{{ __('landing.how_step_1_desc') }}</p>
            </div>
            <div class="text-center">
                <div class="mx-auto inline-flex h-12 w-12 items-center justify-center rounded-full bg-emerald-600 text-lg font-bold text-white shadow-sm">2</div>
                <h3 class="mt-5 text-lg font-semibold text-slate-900">{{ __('landing.how_step_2_title') }}</h3>
                <p class="mt-2 text-sm leading-6 text-slate-600">{{ __('landing.how_step_2_desc') }}</p>
            </div>
            <div class="text-center">
                <div class="mx-auto inline-flex h-12 w-12 items-center justify-center rounded-full bg-emerald-600 text-lg font-bold text-white shadow-sm">3</div>
                <h3 class="mt-5 text-lg font-semibold text-slate-900">{{ __('landing.how_step_3_title') }}</h3>
                <p class="mt-2 text-sm leading-6 text-slate-600">{{ __('landing.how_step_3_desc') }}</p>
            </div>
        </div>
    </div>
</section>

{{-- Final CTA --}}
<section class="bg-slate-900 py-24">
    <div class="mx-auto max-w-7xl px-6 lg:px-8">
        <div class="mx-auto max-w-3xl text-center">
            <h2 class="text-3xl font-bold tracking-tight text-white sm:text-4xl">
                {{ __('landing.final_cta_title') }}
            </h2>
            <p class="mt-4 text-lg leading-8 text-slate-300">
                {{ __('landing.final_cta_subtitle') }}
            </p>
            <a href="mailto:info@bjptechnologies.co.tz?subject=PMS%20demo%20request" class="mt-10 inline-flex items-center rounded-md bg-emerald-500 px-6 py-3 text-base font-semibold text-white shadow-sm transition hover:bg-emerald-400">
                {{ __('landing.final_cta_button') }}
            </a>
        </div>
    </div>
</section>

{{-- Footer --}}
<footer id="contact" class="border-t border-slate-100 bg-white">
    <div class="mx-auto max-w-7xl px-6 py-12 lg:px-8">
        <div class="flex flex-col items-center justify-between gap-4 md:flex-row">
            <div class="flex items-center gap-2 text-sm text-slate-600">
                <span class="inline-flex h-7 w-7 items-center justify-center rounded-md bg-emerald-600 text-xs font-bold text-white">P</span>
                <span class="font-semibold text-slate-900">{{ __('landing.brand_name') }}</span>
                <span class="hidden sm:inline">&mdash; {{ __('landing.footer_tagline') }}</span>
            </div>
            <p class="text-sm text-slate-500">
                &copy; {{ date('Y') }} {{ __('landing.footer_company') }} {{ __('landing.footer_rights') }}
            </p>
        </div>
    </div>
</footer>

</body>
</html>
