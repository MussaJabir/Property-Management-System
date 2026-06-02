<div class="space-y-10">
    {{-- ───────────────── Marketplace hero (search-led) ───────────────── --}}
    <section class="relative overflow-hidden rounded-3xl text-white shadow-lg ring-1 ring-black/5"
             style="background: linear-gradient(135deg, var(--brand) 0%, color-mix(in srgb, var(--brand) 65%, black) 100%);">
        {{-- Decorative wash --}}
        <div aria-hidden="true" class="absolute -right-20 -top-20 h-72 w-72 rounded-full bg-white/10 blur-3xl"></div>
        <div aria-hidden="true" class="absolute -bottom-24 -left-16 h-72 w-72 rounded-full bg-black/10 blur-3xl"></div>

        <div class="relative px-6 py-12 sm:px-10 sm:py-16 lg:px-14 lg:py-20">
            <p class="text-xs font-bold uppercase tracking-[0.18em] text-white/80">{{ __('Available now') }}</p>
            <h1 class="mt-3 font-extrabold leading-[0.95] tracking-tight"
                style="font-size: clamp(2.25rem, 6vw, 4.5rem);">
                {{ __('Find your next home.') }}
            </h1>
            <p class="mt-4 max-w-xl text-base text-white/85 sm:text-lg">
                {{ __('Browse vacant units, check the rent and size, and reach out — all in one place.') }}
            </p>

            {{-- Search bar (the marketplace CTA) --}}
            <div class="mt-8 flex max-w-2xl items-center gap-2 rounded-full bg-white p-2 shadow-xl ring-1 ring-black/5">
                <div class="flex flex-1 items-center gap-2 pl-3">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-5 w-5 shrink-0 text-zinc-400">
                        <path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 1 0 0 11 5.5 5.5 0 0 0 0-11ZM2 9a7 7 0 1 1 12.452 4.391l3.328 3.329a.75.75 0 1 1-1.06 1.06l-3.329-3.328A7 7 0 0 1 2 9Z" clip-rule="evenodd" /></svg>
                    <input wire:model.live.debounce.400ms="search" type="search"
                           placeholder="{{ __('Search by unit, building, or area…') }}"
                           class="min-h-[44px] w-full bg-transparent text-sm text-zinc-900 placeholder:text-zinc-400 focus:outline-none">
                </div>
                <a href="#listings"
                   class="inline-flex min-h-[44px] cursor-pointer items-center rounded-full px-5 text-sm font-semibold text-white shadow-sm transition-opacity hover:opacity-90"
                   style="background-color: var(--brand);">
                    {{ __('Search') }}
                </a>
            </div>

            {{-- Category pills (property types as quick filters) --}}
            @if (! empty($types))
                <div class="mt-6 flex flex-wrap items-center gap-2">
                    <button wire:click="$set('type', '')" type="button"
                            class="inline-flex min-h-[36px] cursor-pointer items-center rounded-full px-3.5 text-xs font-semibold transition-colors {{ ! $type ? 'bg-white text-zinc-900' : 'bg-white/15 text-white hover:bg-white/25' }}">
                        {{ __('All') }}
                    </button>
                    @foreach ($types as $t)
                        <button wire:click="$set('type', '{{ $t }}')" type="button"
                                class="inline-flex min-h-[36px] cursor-pointer items-center rounded-full px-3.5 text-xs font-semibold capitalize transition-colors {{ $type === $t ? 'bg-white text-zinc-900' : 'bg-white/15 text-white hover:bg-white/25' }}">
                            {{ str_replace('_', ' ', $t) }}
                        </button>
                    @endforeach
                </div>
            @endif
        </div>
    </section>

    {{-- ───────────────── Detailed filters ───────────────── --}}
    <details class="group rounded-2xl bg-white shadow-sm ring-1 ring-zinc-200 dark:bg-zinc-900 dark:ring-white/10">
        <summary class="flex cursor-pointer list-none items-center justify-between p-4 sm:p-5">
            <span class="inline-flex items-center gap-2 text-sm font-semibold text-zinc-900 dark:text-zinc-100">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4 text-zinc-500">
                    <path fill-rule="evenodd" d="M2.628 1.601C5.028 1.206 7.49 1 10 1s4.973.206 7.372.601a.75.75 0 0 1 .628.74v2.288a2.25 2.25 0 0 1-.659 1.59l-4.682 4.683a2.25 2.25 0 0 0-.659 1.59v3.037c0 .684-.31 1.33-.844 1.757l-1.937 1.55A.75.75 0 0 1 8 18.25v-5.757a2.25 2.25 0 0 0-.659-1.591L2.659 6.22A2.25 2.25 0 0 1 2 4.629V2.34a.75.75 0 0 1 .628-.74Z" clip-rule="evenodd" /></svg>
                {{ __('More filters') }}
            </span>
            <span class="text-xs text-zinc-500 transition-transform group-open:rotate-180">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4">
                    <path fill-rule="evenodd" d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" /></svg>
            </span>
        </summary>
        <div class="grid gap-3 border-t border-zinc-100 p-4 sm:grid-cols-2 sm:p-5 md:grid-cols-3 dark:border-zinc-800">
            <select wire:model.live="locationId" class="min-h-[44px] cursor-pointer rounded-lg border border-zinc-300 bg-white px-3 text-sm dark:border-zinc-700 dark:bg-zinc-950">
                <option value="">{{ __('Any location') }}</option>
                @foreach ($locations as $loc)
                    <option value="{{ $loc->id }}">{{ $loc->region }} · {{ $loc->district }}</option>
                @endforeach
            </select>

            <input wire:model.live.debounce.400ms="minRent" type="number" placeholder="{{ __('Min rent') }}"
                   class="min-h-[44px] rounded-lg border border-zinc-300 bg-white px-3 text-sm placeholder:text-zinc-400 dark:border-zinc-700 dark:bg-zinc-950">

            <input wire:model.live.debounce.400ms="maxRent" type="number" placeholder="{{ __('Max rent') }}"
                   class="min-h-[44px] rounded-lg border border-zinc-300 bg-white px-3 text-sm placeholder:text-zinc-400 dark:border-zinc-700 dark:bg-zinc-950">
        </div>
    </details>

    {{-- ───────────────── Result count + sort row ───────────────── --}}
    <div id="listings" class="flex items-baseline justify-between">
        <h2 class="text-xl font-bold tracking-tight sm:text-2xl">
            @if ($units->total() > 0)
                {{ $units->total() }} {{ trans_choice('unit|units', $units->total()) }} {{ __('available') }}
            @else
                {{ __('No matching units') }}
            @endif
        </h2>
    </div>

    {{-- ───────────────── Results grid ───────────────── --}}
    @if ($units->isEmpty())
        <div class="rounded-2xl bg-white p-16 text-center shadow-sm ring-1 ring-zinc-200 dark:bg-zinc-900 dark:ring-white/10">
            <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full"
                 style="background: color-mix(in srgb, var(--brand) 14%, white);">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" class="h-6 w-6" style="color: var(--brand);">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" /></svg>
            </div>
            <p class="text-sm font-semibold text-zinc-700 dark:text-zinc-300">{{ __('No units match those filters.') }}</p>
            <p class="mt-1 text-xs text-zinc-500">{{ __('Try clearing the search or widening your budget.') }}</p>
            <button wire:click="$set('search', '')" type="button"
                    class="mt-5 inline-flex min-h-[44px] cursor-pointer items-center rounded-full bg-zinc-900 px-5 text-sm font-semibold text-white transition-colors hover:bg-zinc-700">
                {{ __('Clear filters') }}
            </button>
        </div>
    @else
        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($units as $unit)
                @php
                    $thumb = $unit->property?->getFirstMediaUrl('photos', 'thumb');
                    $full = $unit->property?->getFirstMediaUrl('photos');
                    $img = $thumb ?: $full;
                @endphp
                <article class="group cursor-pointer overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-zinc-200 transition-all duration-200 hover:-translate-y-1 hover:shadow-xl hover:ring-zinc-300 dark:bg-zinc-900 dark:ring-white/10 dark:hover:ring-white/20">
                    {{-- Photo area --}}
                    <div class="relative aspect-[16/10] w-full overflow-hidden">
                        @if ($img)
                            <img src="{{ $img }}" alt="{{ $unit->property?->name }} — {{ $unit->code }}"
                                 class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105"
                                 loading="lazy">
                        @else
                            {{-- Branded gradient fallback when no photo --}}
                            <div class="flex h-full w-full items-center justify-center"
                                 style="background: linear-gradient(135deg, color-mix(in srgb, var(--brand) 22%, white) 0%, color-mix(in srgb, var(--brand) 10%, white) 100%);">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.4" stroke="currentColor" class="h-14 w-14" style="color: color-mix(in srgb, var(--brand) 70%, transparent);">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21" /></svg>
                            </div>
                        @endif

                        {{-- Vacancy badge (top-left) --}}
                        <div class="absolute left-3 top-3 inline-flex items-center gap-1.5 rounded-full bg-white/95 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider text-emerald-700 shadow-sm ring-1 ring-emerald-200">
                            <span class="relative flex h-1.5 w-1.5">
                                <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-500 opacity-75"></span>
                                <span class="relative inline-flex h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                            </span>
                            {{ __('Available') }}
                        </div>

                        {{-- Type badge (top-right) --}}
                        @if ($unit->type)
                            <div class="absolute right-3 top-3 inline-flex items-center rounded-full bg-zinc-900/85 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider text-white backdrop-blur-sm">
                                {{ str_replace('_', ' ', $unit->type) }}
                            </div>
                        @endif
                    </div>

                    {{-- Body --}}
                    <div class="p-5">
                        <div class="min-w-0">
                            <h3 class="truncate text-base font-bold text-zinc-900 dark:text-zinc-100">{{ $unit->code }}</h3>
                            <p class="mt-0.5 truncate text-xs text-zinc-500">
                                {{ $unit->property?->name }}@if ($unit->property?->location?->region) · {{ $unit->property->location->region }} @endif
                            </p>
                        </div>

                        <div class="mt-4 flex items-baseline gap-1.5">
                            <span class="text-2xl font-extrabold tracking-tight" style="color: var(--brand);">
                                {{ $unit->rent_currency }} {{ number_format($unit->rent_amount / 100, 0, '.', ',') }}
                            </span>
                            <span class="text-xs font-medium text-zinc-500">/ {{ str_replace('_', ' ', $unit->billing_cycle) }}</span>
                        </div>

                        @if ($unit->bedrooms || $unit->bathrooms || $unit->size_sqm)
                            <div class="mt-4 flex items-center gap-4 border-t border-zinc-100 pt-4 text-xs text-zinc-600 dark:border-zinc-800 dark:text-zinc-400">
                                @if ($unit->bedrooms)
                                    <span class="inline-flex items-center gap-1.5" aria-label="{{ $unit->bedrooms }} {{ __('bedrooms') }}">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-3.5 w-3.5 text-zinc-400"><path d="M2.25 4.5a.75.75 0 0 1 1.5 0v8.25h12V4.5a.75.75 0 0 1 1.5 0v9.75a.75.75 0 0 1-.75.75H3a.75.75 0 0 1-.75-.75V4.5Z" /></svg>
                                        <span class="font-semibold">{{ $unit->bedrooms }}</span> {{ __('bd') }}
                                    </span>
                                @endif
                                @if ($unit->bathrooms)
                                    <span class="inline-flex items-center gap-1.5" aria-label="{{ $unit->bathrooms }} {{ __('bathrooms') }}">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-3.5 w-3.5 text-zinc-400"><path fill-rule="evenodd" d="M4.5 2A1.5 1.5 0 0 0 3 3.5v.5a.75.75 0 0 0 1.5 0v-.5h11v6h-11v-2a.75.75 0 0 0-1.5 0v3a.75.75 0 0 0 .75.75h12.5a.75.75 0 0 0 .75-.75v-7A1.5 1.5 0 0 0 15.5 2h-11Z" clip-rule="evenodd" /></svg>
                                        <span class="font-semibold">{{ $unit->bathrooms }}</span> {{ __('ba') }}
                                    </span>
                                @endif
                                @if ($unit->size_sqm)
                                    <span class="inline-flex items-center gap-1.5" aria-label="{{ $unit->size_sqm }} square metres">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-3.5 w-3.5 text-zinc-400"><path fill-rule="evenodd" d="M2 4.75A.75.75 0 0 1 2.75 4h14.5a.75.75 0 0 1 0 1.5H2.75A.75.75 0 0 1 2 4.75Zm0 5A.75.75 0 0 1 2.75 9h14.5a.75.75 0 0 1 0 1.5H2.75A.75.75 0 0 1 2 9.75Zm0 5a.75.75 0 0 1 .75-.75h14.5a.75.75 0 0 1 0 1.5H2.75A.75.75 0 0 1 2 14.75Z" clip-rule="evenodd" /></svg>
                                        <span class="font-semibold">{{ $unit->size_sqm }}</span> m²
                                    </span>
                                @endif
                            </div>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>

        <div class="pt-2">{{ $units->links() }}</div>
    @endif
</div>
