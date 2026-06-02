@props(['data' => []])

@php
    $client = tenant();
    $heading = $data['heading'] ?? ($client?->name ?? '');
    $subheading = $data['subheading'] ?? '';
    $ctaLabel = $data['cta_label'] ?? null;
    $ctaLink = $data['cta_link'] ?? null;
    $href = $ctaLink ? url('/'.$client->slug.'/'.ltrim($ctaLink, '/')) : null;
@endphp

<section class="relative overflow-hidden rounded-3xl text-white shadow-lg ring-1 ring-black/5"
         style="background: linear-gradient(135deg, var(--brand) 0%, color-mix(in srgb, var(--brand) 65%, black) 100%);">
    {{-- Decorative atmospheric wash --}}
    <div aria-hidden="true" class="absolute -right-24 -top-24 h-80 w-80 rounded-full bg-white/10 blur-3xl"></div>
    <div aria-hidden="true" class="absolute -bottom-32 -left-20 h-80 w-80 rounded-full bg-black/10 blur-3xl"></div>

    <div class="relative px-6 py-14 sm:px-10 sm:py-20 lg:px-14 lg:py-28">
        <h1 class="font-extrabold leading-[0.95] tracking-tight"
            style="font-size: clamp(2.5rem, 7vw, 5.5rem);">
            {{ $heading }}
        </h1>
        @if ($subheading)
            <p class="mt-5 max-w-2xl text-base leading-relaxed text-white/85 sm:text-lg">
                {{ $subheading }}
            </p>
        @endif
        @if ($ctaLabel && $href)
            <a href="{{ $href }}"
               class="mt-8 inline-flex min-h-[48px] cursor-pointer items-center gap-2 rounded-full bg-white px-6 text-sm font-bold text-zinc-900 shadow-lg ring-1 ring-black/5 transition-colors hover:bg-zinc-100 sm:text-base">
                {{ $ctaLabel }}
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4">
                    <path fill-rule="evenodd" d="M3 10a.75.75 0 0 1 .75-.75h10.638L10.23 5.29a.75.75 0 1 1 1.04-1.08l5.5 5.25a.75.75 0 0 1 0 1.08l-5.5 5.25a.75.75 0 1 1-1.04-1.08l4.158-3.96H3.75A.75.75 0 0 1 3 10Z" clip-rule="evenodd" /></svg>
            </a>
        @endif
    </div>
</section>
