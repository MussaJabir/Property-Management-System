@props(['data' => []])

@php
    $client = tenant();
    $heading = $data['heading'] ?? ($client?->name ?? '');
    $subheading = $data['subheading'] ?? '';
    $ctaLabel = $data['cta_label'] ?? null;
    $ctaLink = $data['cta_link'] ?? null;
    $href = $ctaLink ? url('/'.$client->slug.'/'.ltrim($ctaLink, '/')) : null;
@endphp

<section class="relative overflow-hidden rounded-2xl text-white shadow-lg" style="background: linear-gradient(135deg, var(--brand) 0%, color-mix(in srgb, var(--brand) 70%, black) 100%);">
    <div class="relative z-10 px-8 py-16 sm:px-12 sm:py-24">
        <h1 class="text-3xl font-bold sm:text-5xl">{{ $heading }}</h1>
        @if ($subheading)
            <p class="mt-4 max-w-2xl text-base opacity-90 sm:text-lg">{{ $subheading }}</p>
        @endif
        @if ($ctaLabel && $href)
            <a href="{{ $href }}" class="mt-8 inline-flex items-center gap-2 rounded-md bg-white px-5 py-2.5 text-sm font-semibold text-zinc-900 shadow transition hover:bg-zinc-100">
                {{ $ctaLabel }} →
            </a>
        @endif
    </div>
</section>
