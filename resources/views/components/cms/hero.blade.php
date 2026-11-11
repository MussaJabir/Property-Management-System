@props(['data' => []])

@php
    use App\Support\Files;

    $client = tenant();
    $heading = $data['heading'] ?? ($client?->name ?? '');
    $subheading = $data['subheading'] ?? '';
    // The button always leads to the rooms; nothing for the landlord to set up.
    $href = url('/'.$client->slug.'/units');

    // Background image: a path on the public disk (B2 public bucket in production).
    $bgPath = $data['background_image'] ?? null;
    $bgUrl = Files::publicUrl($bgPath);
    // Which part of the photo stays in view when it is cropped.
    $position = match ($data['focus'] ?? null) {
        'top' => 'center top',
        'bottom' => 'center bottom',
        default => 'center',
    };
    // A tall photo can't fill a wide banner without losing most of it, so on
    // computers it shows whole beside the title instead.
    $tall = $bgUrl !== null && Files::isTallImage($bgPath);
@endphp

@if ($bgUrl)
    {{-- ─────── With uploaded background image: full-bleed photo + editorial overlay ─────── --}}
    <section @class([
                 'relative overflow-hidden rounded-[28px] shadow-[0_24px_60px_-24px_rgba(0,0,0,0.35)] ring-1 ring-black/5',
                 'lg:hidden' => $tall,
             ])
             style="background-image: url('{{ $bgUrl }}'); background-size: cover; background-position: {{ $position }}; min-height: clamp(360px, 60vh, 620px);">

        {{-- Multi-stop overlay: brand wash at top + black at bottom for legible text. --}}
        <div aria-hidden="true" class="absolute inset-0"
             style="background:
                 linear-gradient(to top, rgba(0,0,0,0.78) 0%, rgba(0,0,0,0.35) 45%, transparent 80%),
                 linear-gradient(135deg, color-mix(in srgb, var(--brand) 25%, transparent) 0%, transparent 55%);"></div>

        {{-- Decorative corner mark (editorial flourish) --}}
        <div aria-hidden="true" class="absolute right-6 top-6 hidden h-14 w-14 rounded-full border border-white/40 sm:block">
            <div class="absolute inset-2 rounded-full border border-white/20"></div>
        </div>

        {{-- Content anchored to bottom-left --}}
        <div class="relative flex h-full min-h-[inherit] flex-col justify-end p-7 sm:p-10 lg:p-14">
            <span class="font-mono-ui text-[10px] font-semibold uppercase tracking-[0.2em] text-white/85">
                <span class="inline-block h-1.5 w-1.5 align-middle rounded-full bg-white"></span>
                &nbsp;{{ __('Welcome') }} &nbsp;·&nbsp; {{ __('Available now') }}
            </span>
            <h1 class="mt-4 max-w-3xl font-display font-extrabold leading-[0.95] tracking-tight text-white"
                style="font-size: clamp(2.25rem, 6.5vw, 5rem);">
                {{ $heading }}
            </h1>
            @if ($subheading)
                <p class="mt-5 max-w-xl text-base leading-relaxed text-white/90 sm:text-lg">
                    {{ $subheading }}
                </p>
            @endif
            <div class="mt-7">
                <x-cms.hero-button :href="$href" />
            </div>
        </div>
    </section>

    @if ($tall)
        {{-- ─────── Tall photo on computers: title on the brand colour, the whole photo beside it ─────── --}}
        <section class="hidden overflow-hidden rounded-[28px] shadow-[0_24px_60px_-24px_rgba(0,0,0,0.35)] ring-1 ring-black/5 lg:grid lg:grid-cols-[minmax(0,1.1fr)_minmax(0,1fr)]"
                 style="min-height: clamp(440px, 64vh, 620px);">
            <div class="relative flex flex-col justify-center p-14"
                 style="background:
                     radial-gradient(at 0% 100%, color-mix(in srgb, var(--brand) 55%, black) 0%, transparent 60%),
                     linear-gradient(135deg, var(--brand) 0%, color-mix(in srgb, var(--brand) 78%, black) 100%);">
                <span class="font-mono-ui text-[10px] font-semibold uppercase tracking-[0.2em] text-white/85">
                    <span class="inline-block h-1.5 w-1.5 align-middle rounded-full bg-white"></span>
                    &nbsp;{{ __('Welcome') }} &nbsp;·&nbsp; {{ __('Available now') }}
                </span>
                {{-- Only one of the two banners is displayed at a time, so only one h1 is read out. --}}
                <h1 class="mt-4 font-display font-extrabold leading-[0.95] tracking-tight text-white" style="font-size: clamp(2.5rem, 4.6vw, 4.25rem);">
                    {{ $heading }}
                </h1>
                @if ($subheading)
                    <p class="mt-5 max-w-md text-lg leading-relaxed text-white/90">{{ $subheading }}</p>
                @endif
                <div class="mt-8">
                    <x-cms.hero-button :href="$href" />
                </div>
            </div>
            <div class="relative overflow-hidden bg-zinc-100">
                {{-- A soft, blurred copy behind fills the space around the photo in its own colours. --}}
                <div aria-hidden="true" class="absolute inset-0 scale-125 bg-cover bg-center opacity-70 blur-3xl" style="background-image: url('{{ $bgUrl }}');"></div>
                <img src="{{ $bgUrl }}" alt="{{ $client?->name }}" class="absolute inset-0 h-full w-full object-contain p-6">
            </div>
        </section>
    @endif
@else
    {{-- ─────── Without image: massive editorial brand-color block ─────── --}}
    <section class="relative overflow-hidden rounded-[28px] shadow-[0_24px_60px_-24px_rgba(0,0,0,0.25)] ring-1 ring-black/5"
             style="background:
                 radial-gradient(at 100% 0%, color-mix(in srgb, var(--brand) 60%, white) 0%, transparent 60%),
                 linear-gradient(135deg, var(--brand) 0%, color-mix(in srgb, var(--brand) 75%, black) 100%);">

        {{-- Decorative geometric mark (top-right) --}}
        <div aria-hidden="true" class="absolute -right-10 -top-10 h-48 w-48 rounded-full border border-white/15 sm:h-64 sm:w-64"></div>
        <div aria-hidden="true" class="absolute -right-2 -top-2 h-24 w-24 rounded-full border border-white/30 sm:h-32 sm:w-32"></div>
        <div aria-hidden="true" class="absolute right-10 top-10 hidden h-2 w-2 rounded-full bg-white/80 sm:block"></div>

        {{-- Decorative crosshair (bottom-left) --}}
        <div aria-hidden="true" class="absolute bottom-5 left-5 hidden text-white/20 lg:block">
            <svg width="48" height="48" viewBox="0 0 48 48" fill="none">
                <path d="M24 4v40M4 24h40" stroke="currentColor" stroke-width="1"/>
                <circle cx="24" cy="24" r="3" stroke="currentColor" stroke-width="1"/>
            </svg>
        </div>

        <div class="relative px-6 py-14 sm:px-12 sm:py-20 lg:px-16 lg:py-24">
            <span class="font-mono-ui text-[10px] font-semibold uppercase tracking-[0.22em] text-white/80">
                <span class="inline-block h-1.5 w-1.5 align-middle rounded-full bg-white/95"></span>
                &nbsp;{{ __('Properties') }} &nbsp;·&nbsp; {{ $client?->name ?? '' }}
            </span>

            <h1 class="mt-5 max-w-4xl font-display font-extrabold leading-[0.92] tracking-tight text-white"
                style="font-size: clamp(2.5rem, 8.5vw, 6.5rem);">
                {{ $heading }}
            </h1>

            @if ($subheading)
                <p class="mt-6 max-w-2xl text-base leading-relaxed text-white/90 sm:text-lg">
                    {{ $subheading }}
                </p>
            @endif

            <div class="mt-9">
                <x-cms.hero-button :href="$href" />
            </div>
        </div>
    </section>
@endif
