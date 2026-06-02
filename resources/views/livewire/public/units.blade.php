<div class="space-y-12 lg:space-y-16">
    {{-- ═══════════════════════════════════════ HEADER + SEARCH ═══ --}}
    <header class="space-y-5">
        <div>
            <span class="inline-flex items-center gap-2 font-mono-ui text-[10px] font-semibold uppercase tracking-[0.22em] text-zinc-500">
                <span class="inline-block h-1.5 w-1.5 rounded-full" style="background-color: var(--brand);"></span>
                {{ __('Properties') }}
            </span>
            <h1 class="mt-3 font-display font-extrabold leading-[0.92] tracking-tight text-zinc-900"
                style="font-size: clamp(2.5rem, 7vw, 5rem);">
                {{ __('Find your next home.') }}
            </h1>
            <p class="mt-3 max-w-xl text-base leading-relaxed text-zinc-600 sm:text-lg">
                {{ __('Browse vacant units. Filter by area, type, and budget — all in one place.') }}
            </p>
        </div>

        {{-- Search bar: editorial outlined field with brand-color submit pill --}}
        <div class="flex max-w-3xl items-center gap-2 rounded-full bg-white p-2 shadow-[0_4px_24px_rgba(0,0,0,0.06)] ring-1 ring-zinc-900/[0.08]">
            <div class="flex flex-1 items-center gap-2.5 pl-3">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-5 w-5 shrink-0 text-zinc-400">
                    <path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 1 0 0 11 5.5 5.5 0 0 0 0-11ZM2 9a7 7 0 1 1 12.452 4.391l3.328 3.329a.75.75 0 1 1-1.06 1.06l-3.329-3.328A7 7 0 0 1 2 9Z" clip-rule="evenodd" /></svg>
                <input wire:model.live.debounce.400ms="search" type="search"
                       placeholder="{{ __('Search by unit, building, or area…') }}"
                       class="min-h-[44px] w-full bg-transparent text-sm text-zinc-900 placeholder:text-zinc-400 focus:outline-none">
            </div>
            <a href="#listings"
               class="inline-flex min-h-[44px] cursor-pointer items-center rounded-full px-5 text-xs font-bold uppercase tracking-[0.08em] text-white shadow-sm transition-opacity hover:opacity-90"
               style="background-color: var(--brand);">{{ __('Search') }}</a>
        </div>

        {{-- Category pills --}}
        @if (! empty($types))
            <div class="flex flex-wrap items-center gap-2 pt-1">
                <span class="mr-2 font-mono-ui text-[10px] font-semibold uppercase tracking-[0.18em] text-zinc-400">{{ __('Type') }}</span>
                <button wire:click="$set('type', '')" type="button"
                        class="inline-flex min-h-[34px] cursor-pointer items-center rounded-full px-3.5 text-[11px] font-semibold uppercase tracking-[0.05em] transition-colors {{ ! $type ? 'bg-zinc-900 text-white' : 'bg-white text-zinc-700 ring-1 ring-zinc-900/10 hover:ring-zinc-900/25' }}">
                    {{ __('All') }}
                </button>
                @foreach ($types as $t)
                    <button wire:click="$set('type', '{{ $t }}')" type="button"
                            class="inline-flex min-h-[34px] cursor-pointer items-center rounded-full px-3.5 text-[11px] font-semibold uppercase tracking-[0.05em] capitalize transition-colors {{ $type === $t ? 'bg-zinc-900 text-white' : 'bg-white text-zinc-700 ring-1 ring-zinc-900/10 hover:ring-zinc-900/25' }}">
                        {{ str_replace('_', ' ', $t) }}
                    </button>
                @endforeach
            </div>
        @endif
    </header>

    {{-- ═══════════════════════════════════════ ADVANCED FILTERS ═══ --}}
    <details class="group rounded-2xl bg-white shadow-[0_4px_24px_rgba(0,0,0,0.04)] ring-1 ring-zinc-900/[0.06]">
        <summary class="flex cursor-pointer list-none items-center justify-between p-4 sm:p-5">
            <span class="inline-flex items-center gap-2.5 font-mono-ui text-[11px] font-semibold uppercase tracking-[0.15em] text-zinc-700">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4 text-zinc-500">
                    <path fill-rule="evenodd" d="M2.628 1.601C5.028 1.206 7.49 1 10 1s4.973.206 7.372.601a.75.75 0 0 1 .628.74v2.288a2.25 2.25 0 0 1-.659 1.59l-4.682 4.683a2.25 2.25 0 0 0-.659 1.59v3.037c0 .684-.31 1.33-.844 1.757l-1.937 1.55A.75.75 0 0 1 8 18.25v-5.757a2.25 2.25 0 0 0-.659-1.591L2.659 6.22A2.25 2.25 0 0 1 2 4.629V2.34a.75.75 0 0 1 .628-.74Z" clip-rule="evenodd" /></svg>
                {{ __('More filters') }}
            </span>
            <span class="text-zinc-400 transition-transform group-open:rotate-180">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4">
                    <path fill-rule="evenodd" d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" /></svg>
            </span>
        </summary>
        <div class="grid gap-3 border-t border-zinc-100 p-4 sm:grid-cols-2 sm:p-5 md:grid-cols-3">
            <select wire:model.live="locationId" class="min-h-[44px] cursor-pointer rounded-lg border border-zinc-200 bg-white px-3 text-sm">
                <option value="">{{ __('Any location') }}</option>
                @foreach ($locations as $loc)
                    <option value="{{ $loc->id }}">{{ $loc->region }} · {{ $loc->district }}</option>
                @endforeach
            </select>
            <input wire:model.live.debounce.400ms="minRent" type="number" placeholder="{{ __('Min rent') }}"
                   class="min-h-[44px] rounded-lg border border-zinc-200 bg-white px-3 text-sm placeholder:text-zinc-400">
            <input wire:model.live.debounce.400ms="maxRent" type="number" placeholder="{{ __('Max rent') }}"
                   class="min-h-[44px] rounded-lg border border-zinc-200 bg-white px-3 text-sm placeholder:text-zinc-400">
        </div>
    </details>

    {{-- ═══════════════════════════════════════ RESULTS BAND ═══ --}}
    <div id="listings" class="brand-rule"><span class="brand-rule__dot"></span></div>

    <div class="flex items-baseline justify-between">
        <h2 class="font-display text-xl font-bold tracking-tight text-zinc-900 sm:text-2xl">
            @if ($units->total() > 0)
                <span class="tnum">{{ $units->total() }}</span> {{ trans_choice('unit|units', $units->total()) }} {{ __('available') }}
            @else
                {{ __('No matching units') }}
            @endif
        </h2>
    </div>

    {{-- ═══════════════════════════════════════ GRID ═══ --}}
    @if ($units->isEmpty())
        <div class="relative overflow-hidden rounded-3xl bg-white p-12 text-center shadow-[0_4px_24px_rgba(0,0,0,0.04)] ring-1 ring-zinc-900/[0.06] sm:p-20">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 80 80" class="mx-auto h-24 w-24"
                 style="color: color-mix(in srgb, var(--brand) 45%, transparent);">
                <rect x="14" y="22" width="20" height="44" rx="1.5" stroke="currentColor" stroke-width="1.4"/>
                <rect x="36" y="14" width="30" height="52" rx="1.5" stroke="currentColor" stroke-width="1.4"/>
                <rect x="40" y="20" width="6" height="6" rx="0.5" fill="currentColor" opacity="0.3"/>
                <rect x="48" y="20" width="6" height="6" rx="0.5" fill="currentColor" opacity="0.3"/>
                <rect x="56" y="20" width="6" height="6" rx="0.5" fill="currentColor" opacity="0.3"/>
                <rect x="40" y="30" width="6" height="6" rx="0.5" fill="currentColor" opacity="0.3"/>
                <rect x="48" y="30" width="6" height="6" rx="0.5" fill="currentColor" opacity="0.3"/>
                <rect x="56" y="30" width="6" height="6" rx="0.5" fill="currentColor" opacity="0.3"/>
                <rect x="40" y="40" width="6" height="6" rx="0.5" fill="currentColor" opacity="0.3"/>
                <rect x="48" y="40" width="6" height="6" rx="0.5" fill="currentColor" opacity="0.3"/>
                <rect x="56" y="40" width="6" height="6" rx="0.5" fill="currentColor" opacity="0.3"/>
                <rect x="18" y="30" width="4" height="4" rx="0.5" fill="currentColor" opacity="0.3"/>
                <rect x="26" y="30" width="4" height="4" rx="0.5" fill="currentColor" opacity="0.3"/>
                <rect x="18" y="40" width="4" height="4" rx="0.5" fill="currentColor" opacity="0.3"/>
                <rect x="26" y="40" width="4" height="4" rx="0.5" fill="currentColor" opacity="0.3"/>
                <rect x="18" y="50" width="4" height="4" rx="0.5" fill="currentColor" opacity="0.3"/>
                <rect x="26" y="50" width="4" height="4" rx="0.5" fill="currentColor" opacity="0.3"/>
                <path d="M6 66h68" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/>
            </svg>
            <h3 class="mt-6 font-display text-2xl font-bold text-zinc-900">{{ __('No vacancies just now.') }}</h3>
            <p class="mx-auto mt-2 max-w-md text-sm text-zinc-600 sm:text-base">{{ __('Get in touch and we will let you know the moment something opens up that matches what you are looking for.') }}</p>
            <div class="mt-7 flex flex-wrap items-center justify-center gap-3">
                <button wire:click="$set('search', '')" type="button"
                        class="inline-flex min-h-[44px] cursor-pointer items-center rounded-full bg-zinc-900 px-5 text-sm font-bold tracking-tight text-white transition-opacity hover:opacity-90">
                    {{ __('Clear filters') }}
                </button>
                <a href="{{ url('/'.tenant()->slug.'/contact') }}"
                   class="inline-flex min-h-[44px] cursor-pointer items-center rounded-full border border-zinc-900/15 bg-white px-5 text-sm font-semibold text-zinc-900 transition-colors hover:bg-zinc-50">
                    {{ __('Contact us') }}
                </a>
            </div>
        </div>
    @else
        <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($units as $unit)
                @php
                    $thumb = $unit->property?->getFirstMediaUrl('photos', 'thumb');
                    $full = $unit->property?->getFirstMediaUrl('photos');
                    $img = $thumb ?: $full;
                @endphp
                <article class="group relative flex flex-col overflow-hidden rounded-2xl bg-white shadow-[0_4px_24px_rgba(0,0,0,0.06)] ring-1 ring-zinc-900/[0.06] transition-all duration-300 hover:-translate-y-1 hover:shadow-[0_24px_48px_rgba(0,0,0,0.1)]">
                    {{-- Photo --}}
                    <div class="relative aspect-[4/3] overflow-hidden">
                        @if ($img)
                            <img src="{{ $img }}" alt="{{ $unit->property?->name }} — {{ $unit->code }}"
                                 class="h-full w-full object-cover transition-transform duration-700 group-hover:scale-105"
                                 loading="lazy">
                        @else
                            <div class="flex h-full w-full items-center justify-center"
                                 style="background:
                                     radial-gradient(at 30% 20%, color-mix(in srgb, var(--brand) 25%, white) 0%, transparent 70%),
                                     linear-gradient(135deg, color-mix(in srgb, var(--brand) 18%, #fdfcf9) 0%, color-mix(in srgb, var(--brand) 6%, #fdfcf9) 100%);">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 80 80" class="h-20 w-20" style="color: color-mix(in srgb, var(--brand) 70%, transparent);">
                                    <rect x="14" y="22" width="20" height="44" rx="1.5" stroke="currentColor" stroke-width="1.5"/>
                                    <rect x="36" y="14" width="30" height="52" rx="1.5" stroke="currentColor" stroke-width="1.5"/>
                                    <rect x="40" y="20" width="6" height="6" fill="currentColor" opacity="0.3"/>
                                    <rect x="48" y="20" width="6" height="6" fill="currentColor" opacity="0.3"/>
                                    <rect x="56" y="20" width="6" height="6" fill="currentColor" opacity="0.3"/>
                                </svg>
                            </div>
                        @endif

                        {{-- Editorial vacancy chip (bottom-left, sharp corners) --}}
                        <div class="absolute bottom-3 left-3 inline-flex items-center gap-1.5 bg-white px-2.5 py-1 font-mono-ui text-[9px] font-bold uppercase tracking-[0.12em] text-emerald-700 shadow-sm">
                            <span class="relative inline-block h-1.5 w-1.5">
                                <span class="absolute inline-block h-full w-full animate-ping rounded-full bg-emerald-500 opacity-75"></span>
                                <span class="relative inline-block h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                            </span>
                            {{ __('Available') }}
                        </div>

                        {{-- Type chip (top-right) --}}
                        @if ($unit->type)
                            <div class="absolute right-3 top-3 inline-flex items-center bg-zinc-900/90 px-2.5 py-1 font-mono-ui text-[9px] font-bold uppercase tracking-[0.12em] text-white backdrop-blur-sm">
                                {{ str_replace('_', ' ', $unit->type) }}
                            </div>
                        @endif
                    </div>

                    {{-- Body --}}
                    <div class="flex flex-1 flex-col p-6">
                        <p class="font-mono-ui text-[10px] font-semibold uppercase tracking-[0.18em] text-zinc-500">{{ $unit->property?->location?->region ?? __('Tanzania') }}</p>

                        <h3 class="mt-2 font-display text-lg font-bold tracking-tight text-zinc-900">
                            {{ $unit->property?->name ?? $unit->code }}
                        </h3>
                        <p class="mt-0.5 text-sm text-zinc-500">
                            {{ __('Unit') }} {{ $unit->code }}
                        </p>

                        <div class="mt-5 flex items-baseline justify-between border-t border-zinc-100 pt-5">
                            <div>
                                <span class="font-display text-3xl font-bold leading-none tracking-tight tnum" style="color: var(--brand);">
                                    {{ number_format($unit->rent_amount / 100, 0, '.', ',') }}
                                </span>
                                <span class="ml-1 font-mono-ui text-[10px] uppercase tracking-[0.1em] text-zinc-500">
                                    {{ $unit->rent_currency }} / {{ str_replace('_', ' ', $unit->billing_cycle) }}
                                </span>
                            </div>
                        </div>

                        @if ($unit->bedrooms || $unit->bathrooms || $unit->size_sqm)
                            <div class="mt-4 flex items-center gap-4 font-mono-ui text-[11px] text-zinc-600 tnum">
                                @if ($unit->bedrooms)
                                    <span class="inline-flex items-center gap-1" aria-label="{{ $unit->bedrooms }} {{ __('bedrooms') }}">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-3.5 w-3.5 text-zinc-400"><path d="M2.25 4.5a.75.75 0 0 1 1.5 0v8.25h12V4.5a.75.75 0 0 1 1.5 0v9.75a.75.75 0 0 1-.75.75H3a.75.75 0 0 1-.75-.75V4.5Z" /></svg>
                                        <span class="font-semibold">{{ $unit->bedrooms }}</span> {{ __('bd') }}
                                    </span>
                                @endif
                                @if ($unit->bathrooms)
                                    <span class="inline-flex items-center gap-1" aria-label="{{ $unit->bathrooms }} {{ __('bathrooms') }}">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-3.5 w-3.5 text-zinc-400"><path fill-rule="evenodd" d="M4.5 2A1.5 1.5 0 0 0 3 3.5v.5a.75.75 0 0 0 1.5 0v-.5h11v6h-11v-2a.75.75 0 0 0-1.5 0v3a.75.75 0 0 0 .75.75h12.5a.75.75 0 0 0 .75-.75v-7A1.5 1.5 0 0 0 15.5 2h-11Z" clip-rule="evenodd" /></svg>
                                        <span class="font-semibold">{{ $unit->bathrooms }}</span> {{ __('ba') }}
                                    </span>
                                @endif
                                @if ($unit->size_sqm)
                                    <span class="inline-flex items-center gap-1" aria-label="{{ $unit->size_sqm }} m²">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-3.5 w-3.5 text-zinc-400"><path fill-rule="evenodd" d="M2 4.75A.75.75 0 0 1 2.75 4h14.5a.75.75 0 0 1 0 1.5H2.75A.75.75 0 0 1 2 4.75Zm0 5A.75.75 0 0 1 2.75 9h14.5a.75.75 0 0 1 0 1.5H2.75A.75.75 0 0 1 2 9.75Zm0 5a.75.75 0 0 1 .75-.75h14.5a.75.75 0 0 1 0 1.5H2.75A.75.75 0 0 1 2 14.75Z" clip-rule="evenodd" /></svg>
                                        <span class="font-semibold">{{ $unit->size_sqm }}</span> m²
                                    </span>
                                @endif
                            </div>
                        @endif
                    </div>

                    {{-- Brand-color underline that slides in on hover --}}
                    <div aria-hidden="true" class="absolute bottom-0 left-0 h-[3px] w-0 transition-all duration-500 group-hover:w-full" style="background-color: var(--brand);"></div>
                </article>
            @endforeach
        </div>

        <div class="pt-2">{{ $units->links() }}</div>
    @endif
</div>
