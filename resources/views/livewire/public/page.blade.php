@php
    // Suppress the page-title H1 when the first block is a hero — the hero
    // owns the page's primary H1 already, and a second one above it just
    // creates competing visual weight.
    $firstBlockType = ! empty($page->blocks) ? ($page->blocks[0]['type'] ?? null) : null;
    $skipPageHeader = $firstBlockType === 'hero';
@endphp

<div class="space-y-10 lg:space-y-14">
    @if (! $skipPageHeader && ($page->title || $page->subtitle))
        <header class="space-y-4 text-center sm:text-left">
            <span class="inline-flex items-center gap-2 font-mono-ui text-[10px] font-semibold uppercase tracking-[0.22em] text-zinc-500">
                <span class="inline-block h-1.5 w-1.5 rounded-full" style="background-color: var(--brand);"></span>
                {{ __('Section') }}
            </span>
            <h1 class="font-display font-extrabold leading-[0.95] tracking-tight text-zinc-900"
                style="font-size: clamp(2.25rem, 6vw, 4rem);">
                {{ $page->title }}
            </h1>
            @if ($page->subtitle)
                <p class="max-w-2xl text-base leading-relaxed text-zinc-600 sm:text-lg">{{ $page->subtitle }}</p>
            @endif
        </header>
    @endif

    @if (! empty($page->blocks))
        <x-cms.render-blocks :blocks="$page->blocks" />
    @else
        <div class="relative overflow-hidden rounded-3xl bg-white p-12 text-center shadow-[0_4px_24px_rgba(0,0,0,0.04)] ring-1 ring-zinc-900/[0.06] sm:p-16">
            <div class="mx-auto mb-5 inline-flex h-14 w-14 items-center justify-center rounded-2xl"
                 style="background: color-mix(in srgb, var(--brand) 12%, white);">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.4" stroke="currentColor" class="h-7 w-7" style="color: var(--brand);">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Z" />
                </svg>
            </div>
            <h3 class="font-display text-2xl font-bold text-zinc-900">{{ __('Coming soon') }}</h3>
            <p class="mt-2 text-sm text-zinc-500">{{ __('This page is being prepared. Check back shortly.') }}</p>
        </div>
    @endif
</div>
