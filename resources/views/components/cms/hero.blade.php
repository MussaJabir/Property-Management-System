@props(['data' => []])

@php
    use Illuminate\Support\Facades\Storage;

    $client = tenant();
    $heading = $data['heading'] ?? ($client?->name ?? '');
    $subheading = $data['subheading'] ?? '';
    $ctaLabel = $data['cta_label'] ?? null;
    $ctaLink = $data['cta_link'] ?? null;
    $href = $ctaLink ? url('/'.$client->slug.'/'.ltrim($ctaLink, '/')) : null;

    // Background image is stored in the block JSON as a relative path on the
    // `public` disk (uploaded via the Filament block builder's FileUpload).
    $bgPath = $data['background_image'] ?? null;
    $bgUrl = $bgPath ? Storage::disk('public')->url($bgPath) : null;
@endphp

@if ($bgUrl)
    {{-- ─────── With uploaded background image: full-bleed photo + editorial overlay ─────── --}}
    <section class="relative overflow-hidden rounded-[28px] shadow-[0_24px_60px_-24px_rgba(0,0,0,0.35)] ring-1 ring-black/5"
             style="background-image: url('{{ $bgUrl }}'); background-size: cover; background-position: center; min-height: clamp(360px, 60vh, 620px);">

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
            @if ($ctaLabel && $href)
                <div class="mt-7">
                    <a href="{{ $href }}"
                       class="group inline-flex min-h-[48px] cursor-pointer items-center gap-3 rounded-full bg-white px-6 text-sm font-bold tracking-tight text-zinc-900 shadow-lg ring-1 ring-black/5 transition-transform hover:-translate-y-0.5">
                        {{ $ctaLabel }}
                        <span class="inline-flex h-7 w-7 items-center justify-center rounded-full transition-transform group-hover:translate-x-0.5" style="background-color: var(--brand);">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-3.5 w-3.5 text-white">
                                <path fill-rule="evenodd" d="M3 10a.75.75 0 0 1 .75-.75h10.638L10.23 5.29a.75.75 0 1 1 1.04-1.08l5.5 5.25a.75.75 0 0 1 0 1.08l-5.5 5.25a.75.75 0 1 1-1.04-1.08l4.158-3.96H3.75A.75.75 0 0 1 3 10Z" clip-rule="evenodd" /></svg>
                        </span>
                    </a>
                </div>
            @endif
        </div>
    </section>
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

            @if ($ctaLabel && $href)
                <div class="mt-9">
                    <a href="{{ $href }}"
                       class="group inline-flex min-h-[48px] cursor-pointer items-center gap-3 rounded-full bg-white px-7 text-sm font-bold tracking-tight text-zinc-900 shadow-lg ring-1 ring-black/5 transition-transform hover:-translate-y-0.5">
                        {{ $ctaLabel }}
                        <span class="inline-flex h-7 w-7 items-center justify-center rounded-full transition-transform group-hover:translate-x-0.5" style="background-color: var(--brand);">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-3.5 w-3.5 text-white">
                                <path fill-rule="evenodd" d="M3 10a.75.75 0 0 1 .75-.75h10.638L10.23 5.29a.75.75 0 1 1 1.04-1.08l5.5 5.25a.75.75 0 0 1 0 1.08l-5.5 5.25a.75.75 0 1 1-1.04-1.08l4.158-3.96H3.75A.75.75 0 0 1 3 10Z" clip-rule="evenodd" /></svg>
                        </span>
                    </a>
                </div>
            @endif
        </div>
    </section>
@endif
