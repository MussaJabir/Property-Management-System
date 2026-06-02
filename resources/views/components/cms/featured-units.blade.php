@props(['data' => []])

@php
    use App\Models\Unit;

    $heading = $data['heading'] ?? __('Available now');
    $limit = (int) ($data['limit'] ?? 6);
    $client = tenant();

    $units = Unit::query()
        ->where('status', Unit::STATUS_VACANT)
        ->with(['property.location', 'property.media'])
        ->orderByDesc('updated_at')
        ->limit($limit)
        ->get();
@endphp

<section class="space-y-7">
    {{-- Section header — editorial eyebrow + display heading + "See all" link --}}
    <div class="flex items-end justify-between gap-4">
        <div>
            <span class="inline-flex items-center gap-2 font-mono-ui text-[10px] font-semibold uppercase tracking-[0.22em] text-zinc-500">
                <span class="inline-block h-1.5 w-1.5 rounded-full" style="background-color: var(--brand);"></span>
                {{ __('Available now') }}
            </span>
            <h2 class="mt-2 font-display font-extrabold tracking-tight text-zinc-900"
                style="font-size: clamp(1.75rem, 4vw, 2.5rem);">
                {{ $heading }}
            </h2>
        </div>
        <a href="{{ url('/'.$client->slug.'/units') }}"
           class="group inline-flex shrink-0 items-center gap-2 font-mono-ui text-[11px] font-bold uppercase tracking-[0.12em] text-zinc-900 transition-opacity hover:opacity-70">
            {{ __('See all') }}
            <span class="inline-flex h-7 w-7 items-center justify-center rounded-full transition-transform group-hover:translate-x-0.5"
                  style="background-color: var(--brand);">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-3 w-3 text-white">
                    <path fill-rule="evenodd" d="M3 10a.75.75 0 0 1 .75-.75h10.638L10.23 5.29a.75.75 0 1 1 1.04-1.08l5.5 5.25a.75.75 0 0 1 0 1.08l-5.5 5.25a.75.75 0 1 1-1.04-1.08l4.158-3.96H3.75A.75.75 0 0 1 3 10Z" clip-rule="evenodd" /></svg>
            </span>
        </a>
    </div>

    @if ($units->isEmpty())
        {{-- ─── Polished empty state with character ─── --}}
        <div class="relative overflow-hidden rounded-3xl bg-white p-12 text-center shadow-[0_4px_24px_rgba(0,0,0,0.04)] ring-1 ring-zinc-900/[0.06]">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 80 80" class="mx-auto h-20 w-20"
                 style="color: color-mix(in srgb, var(--brand) 50%, transparent);">
                <rect x="14" y="22" width="20" height="44" rx="1.5" stroke="currentColor" stroke-width="1.4"/>
                <rect x="36" y="14" width="30" height="52" rx="1.5" stroke="currentColor" stroke-width="1.4"/>
                <rect x="40" y="20" width="6" height="6" rx="0.5" fill="currentColor" opacity="0.3"/>
                <rect x="48" y="20" width="6" height="6" rx="0.5" fill="currentColor" opacity="0.3"/>
                <rect x="56" y="20" width="6" height="6" rx="0.5" fill="currentColor" opacity="0.3"/>
                <rect x="18" y="30" width="4" height="4" rx="0.5" fill="currentColor" opacity="0.3"/>
                <rect x="26" y="30" width="4" height="4" rx="0.5" fill="currentColor" opacity="0.3"/>
                <path d="M6 66h68" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/>
            </svg>
            <h3 class="mt-5 font-display text-xl font-bold text-zinc-900">{{ __('Everything is occupied — for now.') }}</h3>
            <p class="mx-auto mt-2 max-w-md text-sm text-zinc-600">
                {{ __('Drop us a line and we will reach out the moment a unit matching your needs becomes available.') }}
            </p>
            <a href="{{ url('/'.$client->slug.'/contact') }}"
               class="mt-5 inline-flex min-h-[44px] cursor-pointer items-center gap-2 rounded-full bg-zinc-900 px-5 text-sm font-bold tracking-tight text-white transition-opacity hover:opacity-90">
                {{ __('Contact us') }}
            </a>
        </div>
    @else
        <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($units as $unit)
                @php
                    $img = $unit->coverImageUrl('thumb') ?: $unit->coverImageUrl();
                @endphp
                <a href="{{ url('/'.$client->slug.'/units/'.$unit->id) }}"
                   class="group relative flex flex-col overflow-hidden rounded-2xl bg-white shadow-[0_4px_24px_rgba(0,0,0,0.06)] ring-1 ring-zinc-900/[0.06] transition-all duration-300 hover:-translate-y-1 hover:shadow-[0_24px_48px_rgba(0,0,0,0.1)]">
                    <div class="relative aspect-[4/3] overflow-hidden">
                        @if ($img)
                            <img src="{{ $img }}" alt="{{ $unit->property?->name }}"
                                 class="h-full w-full object-cover transition-transform duration-700 group-hover:scale-105" loading="lazy">
                        @else
                            <div class="flex h-full w-full items-center justify-center"
                                 style="background:
                                     radial-gradient(at 30% 20%, color-mix(in srgb, var(--brand) 25%, white) 0%, transparent 70%),
                                     linear-gradient(135deg, color-mix(in srgb, var(--brand) 18%, #fdfcf9) 0%, color-mix(in srgb, var(--brand) 6%, #fdfcf9) 100%);">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 80 80" class="h-16 w-16" style="color: color-mix(in srgb, var(--brand) 70%, transparent);">
                                    <rect x="14" y="22" width="20" height="44" rx="1.5" stroke="currentColor" stroke-width="1.5"/>
                                    <rect x="36" y="14" width="30" height="52" rx="1.5" stroke="currentColor" stroke-width="1.5"/>
                                    <rect x="40" y="20" width="6" height="6" fill="currentColor" opacity="0.3"/>
                                    <rect x="48" y="20" width="6" height="6" fill="currentColor" opacity="0.3"/>
                                </svg>
                            </div>
                        @endif
                        <div class="absolute bottom-3 left-3 inline-flex items-center gap-1.5 bg-white px-2.5 py-1 font-mono-ui text-[9px] font-bold uppercase tracking-[0.12em] text-emerald-700 shadow-sm">
                            <span class="relative inline-block h-1.5 w-1.5">
                                <span class="absolute inline-block h-full w-full animate-ping rounded-full bg-emerald-500 opacity-75"></span>
                                <span class="relative inline-block h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                            </span>
                            {{ __('Available') }}
                        </div>
                    </div>

                    <div class="flex flex-1 flex-col p-5">
                        <p class="font-mono-ui text-[10px] font-semibold uppercase tracking-[0.18em] text-zinc-500">
                            {{ $unit->property?->location?->region ?? __('Tanzania') }}
                        </p>
                        <h3 class="mt-2 font-display text-lg font-bold tracking-tight text-zinc-900">{{ $unit->property?->name ?? $unit->code }}</h3>
                        <p class="mt-0.5 text-sm text-zinc-500">{{ __('Unit') }} {{ $unit->code }}</p>

                        <div class="mt-4 flex items-baseline justify-between border-t border-zinc-100 pt-4">
                            <div>
                                <span class="font-display text-2xl font-bold leading-none tracking-tight tnum" style="color: var(--brand);">
                                    {{ number_format($unit->rent_amount / 100, 0, '.', ',') }}
                                </span>
                                <span class="ml-1 font-mono-ui text-[10px] uppercase tracking-[0.1em] text-zinc-500">
                                    {{ $unit->rent_currency }} / {{ str_replace('_', ' ', $unit->billing_cycle) }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div aria-hidden="true" class="absolute bottom-0 left-0 h-[3px] w-0 transition-all duration-500 group-hover:w-full" style="background-color: var(--brand);"></div>
                </a>
            @endforeach
        </div>
    @endif
</section>
