@php
    use Illuminate\Support\Str;

    $client = tenant();
    $hasGallery = $gallery->isNotEmpty();
    $usingPropertyPhotos = ! $unit->hasOwnPhotos() && $hasGallery;
    $rentMajor = number_format($unit->rent_amount / 100, 0, '.', ',');
    $region = $unit->property?->location?->region;
    $district = $unit->property?->location?->district;
@endphp

<div class="space-y-10 lg:space-y-14">
    {{-- Breadcrumb --}}
    <nav class="flex items-center gap-2 font-mono-ui text-[11px] uppercase tracking-[0.12em] text-zinc-500">
        <a href="{{ url('/'.$client->slug.'/units') }}" class="hover:text-zinc-900">{{ __('Units') }}</a>
        <span class="text-zinc-300">/</span>
        <span class="text-zinc-900">{{ $unit->code }}</span>
    </nav>

    {{-- ─────────────── Gallery ─────────────── --}}
    @if ($hasGallery)
        <div x-data="{ active: '{{ $gallery->first()->getUrl('card') }}' }" class="space-y-3">
            <div class="relative aspect-[16/10] w-full overflow-hidden rounded-3xl bg-zinc-100 shadow-[0_24px_60px_-24px_rgba(0,0,0,0.3)] ring-1 ring-black/5 sm:aspect-[16/9]">
                <img :src="active" alt="{{ $unit->property?->name }} — {{ $unit->code }}"
                     class="h-full w-full object-cover transition-opacity duration-300">
                @if ($usingPropertyPhotos)
                    <div class="absolute left-4 top-4 inline-flex items-center bg-white/90 px-2.5 py-1 font-mono-ui text-[9px] font-bold uppercase tracking-[0.12em] text-zinc-700 shadow-sm backdrop-blur-sm">
                        {{ __('Property photos') }}
                    </div>
                @endif
            </div>

            @if ($gallery->count() > 1)
                <div class="flex gap-3 overflow-x-auto pb-1">
                    @foreach ($gallery as $photo)
                        <button type="button"
                                @click="active = '{{ $photo->getUrl('card') }}'"
                                :class="active === '{{ $photo->getUrl('card') }}' ? 'ring-2 ring-offset-2' : 'ring-1 ring-zinc-200 opacity-80 hover:opacity-100'"
                                style="--tw-ring-color: var(--brand);"
                                class="relative h-20 w-28 shrink-0 cursor-pointer overflow-hidden rounded-xl bg-zinc-100 transition">
                            <img src="{{ $photo->getUrl('thumb') }}" alt="" class="h-full w-full object-cover" loading="lazy">
                        </button>
                    @endforeach
                </div>
            @endif
        </div>
    @else
        {{-- No photos anywhere: branded gradient hero block --}}
        <div class="flex aspect-[16/9] w-full items-center justify-center overflow-hidden rounded-3xl ring-1 ring-black/5"
             style="background:
                 radial-gradient(at 30% 20%, color-mix(in srgb, var(--brand) 25%, white) 0%, transparent 70%),
                 linear-gradient(135deg, color-mix(in srgb, var(--brand) 18%, #fdfcf9) 0%, color-mix(in srgb, var(--brand) 6%, #fdfcf9) 100%);">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 80 80" class="h-24 w-24" style="color: color-mix(in srgb, var(--brand) 70%, transparent);">
                <rect x="14" y="22" width="20" height="44" rx="1.5" stroke="currentColor" stroke-width="1.5"/>
                <rect x="36" y="14" width="30" height="52" rx="1.5" stroke="currentColor" stroke-width="1.5"/>
                <rect x="40" y="20" width="6" height="6" fill="currentColor" opacity="0.3"/>
                <rect x="48" y="20" width="6" height="6" fill="currentColor" opacity="0.3"/>
                <rect x="56" y="20" width="6" height="6" fill="currentColor" opacity="0.3"/>
            </svg>
        </div>
    @endif

    {{-- ─────────────── Details ─────────────── --}}
    <div class="grid gap-10 lg:grid-cols-3">
        {{-- Main column --}}
        <div class="space-y-8 lg:col-span-2">
            <div>
                <span class="font-mono-ui text-[10px] font-semibold uppercase tracking-[0.18em] text-zinc-500">
                    {{ str_replace('_', ' ', $unit->type ?? 'unit') }}@if ($region) · {{ $region }}@endif
                </span>
                <h1 class="mt-2 font-display font-extrabold leading-[0.95] tracking-tight text-zinc-900"
                    style="font-size: clamp(2rem, 5vw, 3.25rem);">
                    {{ $unit->property?->name ?? $unit->code }}
                </h1>
                <p class="mt-2 text-base text-zinc-500">
                    {{ __('Unit') }} {{ $unit->code }}@if ($district) · {{ $district }}, {{ $region }}@endif
                </p>
            </div>

            {{-- Specs strip --}}
            @if ($unit->bedrooms || $unit->bathrooms || $unit->size_sqm)
                <div class="flex flex-wrap gap-3">
                    @if ($unit->bedrooms)
                        <div class="flex items-center gap-2 rounded-xl bg-white px-4 py-3 shadow-sm ring-1 ring-zinc-900/[0.06]">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4 text-zinc-400"><path d="M2.25 4.5a.75.75 0 0 1 1.5 0v8.25h12V4.5a.75.75 0 0 1 1.5 0v9.75a.75.75 0 0 1-.75.75H3a.75.75 0 0 1-.75-.75V4.5Z" /></svg>
                            <span class="font-mono-ui text-sm tnum"><span class="font-semibold">{{ $unit->bedrooms }}</span> {{ __('bd') }}</span>
                        </div>
                    @endif
                    @if ($unit->bathrooms)
                        <div class="flex items-center gap-2 rounded-xl bg-white px-4 py-3 shadow-sm ring-1 ring-zinc-900/[0.06]">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4 text-zinc-400"><path fill-rule="evenodd" d="M4.5 2A1.5 1.5 0 0 0 3 3.5v.5a.75.75 0 0 0 1.5 0v-.5h11v6h-11v-2a.75.75 0 0 0-1.5 0v3a.75.75 0 0 0 .75.75h12.5a.75.75 0 0 0 .75-.75v-7A1.5 1.5 0 0 0 15.5 2h-11Z" clip-rule="evenodd" /></svg>
                            <span class="font-mono-ui text-sm tnum"><span class="font-semibold">{{ $unit->bathrooms }}</span> {{ __('ba') }}</span>
                        </div>
                    @endif
                    @if ($unit->size_sqm)
                        <div class="flex items-center gap-2 rounded-xl bg-white px-4 py-3 shadow-sm ring-1 ring-zinc-900/[0.06]">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4 text-zinc-400"><path fill-rule="evenodd" d="M2 4.75A.75.75 0 0 1 2.75 4h14.5a.75.75 0 0 1 0 1.5H2.75A.75.75 0 0 1 2 4.75Zm0 5A.75.75 0 0 1 2.75 9h14.5a.75.75 0 0 1 0 1.5H2.75A.75.75 0 0 1 2 9.75Zm0 5a.75.75 0 0 1 .75-.75h14.5a.75.75 0 0 1 0 1.5H2.75A.75.75 0 0 1 2 14.75Z" clip-rule="evenodd" /></svg>
                            <span class="font-mono-ui text-sm tnum"><span class="font-semibold">{{ $unit->size_sqm }}</span> m²</span>
                        </div>
                    @endif
                </div>
            @endif

            {{-- Amenities --}}
            @if (! empty($unit->amenityLabels()))
                <div>
                    <h2 class="font-display text-xl font-bold tracking-tight text-zinc-900">{{ __('amenities.heading') }}</h2>
                    <div class="mt-4">
                        <x-cms.amenity-list :unit="$unit" />
                    </div>
                </div>
            @endif

            {{-- Description --}}
            @if ($unit->description)
                <div>
                    <h2 class="font-display text-xl font-bold tracking-tight text-zinc-900">{{ __('About this unit') }}</h2>
                    <p class="mt-3 whitespace-pre-line text-base leading-relaxed text-zinc-600">{{ $unit->description }}</p>
                </div>
            @endif
        </div>

        {{-- Sticky rent / enquire card --}}
        <div class="lg:col-span-1">
            <div class="lg:sticky lg:top-24 space-y-4 rounded-2xl bg-white p-6 shadow-[0_4px_24px_rgba(0,0,0,0.06)] ring-1 ring-zinc-900/[0.06]">
                <div>
                    <span class="font-mono-ui text-[10px] font-semibold uppercase tracking-[0.18em] text-zinc-500">{{ __('Rent') }}</span>
                    <div class="mt-1 flex items-baseline gap-1.5">
                        <span class="font-display text-4xl font-bold leading-none tracking-tight tnum" style="color: var(--brand);">{{ $rentMajor }}</span>
                        <span class="font-mono-ui text-xs uppercase tracking-[0.1em] text-zinc-500">{{ $unit->rent_currency }} / {{ str_replace('_', ' ', $unit->billing_cycle) }}</span>
                    </div>
                </div>

                <div class="flex items-center gap-2 border-t border-zinc-100 pt-4">
                    <span class="relative inline-block h-2 w-2">
                        <span class="absolute inline-block h-full w-full animate-ping rounded-full bg-emerald-500 opacity-75"></span>
                        <span class="relative inline-block h-2 w-2 rounded-full bg-emerald-500"></span>
                    </span>
                    <span class="font-mono-ui text-xs font-bold uppercase tracking-[0.1em] text-emerald-700">{{ __('Available') }}</span>
                </div>

                <a href="{{ url('/'.$client->slug.'/contact') }}"
                   class="block w-full cursor-pointer rounded-full px-5 py-3.5 text-center text-sm font-bold tracking-tight text-white shadow-sm transition-opacity hover:opacity-90"
                   style="background-color: var(--brand);">
                    {{ __('Enquire about this unit') }}
                </a>
                @if ($client?->contact_email)
                    <a href="mailto:{{ $client->contact_email }}?subject={{ rawurlencode(__('Enquiry').': '.($unit->property?->name ?? '').' — '.$unit->code) }}"
                       class="block text-center text-xs text-zinc-500 underline decoration-zinc-300 underline-offset-4 hover:decoration-zinc-900">
                        {{ $client->contact_email }}
                    </a>
                @endif
            </div>
        </div>
    </div>

    {{-- ─────────────── More units ─────────────── --}}
    @if ($more->isNotEmpty())
        <div class="space-y-6 border-t border-zinc-900/[0.08] pt-12">
            <h2 class="font-display text-2xl font-bold tracking-tight text-zinc-900">{{ __('More available units') }}</h2>
            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($more as $u)
                    @php $img = $u->coverImageUrl('thumb'); @endphp
                    <a href="{{ url('/'.$client->slug.'/units/'.$u->id) }}"
                       class="group block overflow-hidden rounded-2xl bg-white shadow-[0_4px_24px_rgba(0,0,0,0.06)] ring-1 ring-zinc-900/[0.06] transition-all duration-300 hover:-translate-y-1 hover:shadow-[0_24px_48px_rgba(0,0,0,0.1)]">
                        <div class="relative aspect-[4/3] overflow-hidden">
                            @if ($img)
                                <img src="{{ $img }}" alt="{{ $u->property?->name }}" class="h-full w-full object-cover transition-transform duration-700 group-hover:scale-105" loading="lazy">
                            @else
                                <div class="flex h-full w-full items-center justify-center" style="background: linear-gradient(135deg, color-mix(in srgb, var(--brand) 18%, #fdfcf9) 0%, color-mix(in srgb, var(--brand) 6%, #fdfcf9) 100%);">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 80 80" class="h-14 w-14" style="color: color-mix(in srgb, var(--brand) 70%, transparent);"><rect x="14" y="22" width="20" height="44" rx="1.5" stroke="currentColor" stroke-width="1.5"/><rect x="36" y="14" width="30" height="52" rx="1.5" stroke="currentColor" stroke-width="1.5"/></svg>
                                </div>
                            @endif
                        </div>
                        <div class="p-5">
                            <p class="font-mono-ui text-[10px] font-semibold uppercase tracking-[0.18em] text-zinc-500">{{ $u->property?->location?->region ?? __('Tanzania') }}</p>
                            <h3 class="mt-2 font-display text-lg font-bold tracking-tight text-zinc-900">{{ $u->property?->name ?? $u->code }}</h3>
                            <div class="mt-3 flex items-baseline gap-1.5">
                                <span class="font-display text-2xl font-bold leading-none tracking-tight tnum" style="color: var(--brand);">{{ number_format($u->rent_amount / 100, 0, '.', ',') }}</span>
                                <span class="font-mono-ui text-[10px] uppercase tracking-[0.1em] text-zinc-500">{{ $u->rent_currency }} / {{ str_replace('_', ' ', $u->billing_cycle) }}</span>
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>
        </div>
    @endif
</div>
