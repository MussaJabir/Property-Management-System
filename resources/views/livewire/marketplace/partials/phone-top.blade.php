@php
    // Phones: one search bar (filters open in a sheet) and the type chips.
    $summary = collect([
        match ($stay) { 'night' => __('marketplace.stay_night'), 'month' => __('marketplace.stay_month'), default => __('marketplace.any_stay') },
        $max ? __('marketplace.up_to', ['amount' => 'TZS '.number_format((int) $max)]) : __('marketplace.any_price'),
        $bedrooms ? $bedrooms.'+ '.__('marketplace.bedrooms') : null,
    ])->filter()->implode(' · ');
    $activeChip = $stay === 'night' ? 'night' : $type;
@endphp

<section class="flex flex-col gap-3 border-b border-slate-100 bg-slate-50 px-4 pb-3.5 pt-3 sm:hidden">
    <div class="flex items-center gap-2">
        @if ($searching)
            <button type="button" wire:click="clearFilters" class="inline-flex h-11 w-11 shrink-0 cursor-pointer items-center justify-center rounded-full" aria-label="{{ __('marketplace.back_to_all') }}">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
            </button>
        @endif
        <button type="button" @click="sheet = true"
                class="flex min-h-14 min-w-0 flex-1 cursor-pointer items-center gap-3 rounded-full border border-slate-200 bg-white px-4 text-left shadow-[0_4px_16px_rgba(15,23,42,0.10)]">
            <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
            <span class="flex min-w-0 flex-col">
                <b class="truncate text-[15px]">{{ trim($search) !== '' ? trim($search) : ($landlordName ?? __('marketplace.where_to')) }}</b>
                <span class="truncate text-xs text-slate-500">{{ $summary }}</span>
            </span>
        </button>
        @if ($searching)
            <button type="button" @click="sheet = true" class="inline-flex h-11 w-11 shrink-0 cursor-pointer items-center justify-center rounded-full border border-slate-300 bg-white" aria-label="{{ __('marketplace.filters') }}">
                <svg class="h-[18px] w-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 6h16M7 12h10M10 18h4"/></svg>
            </button>
        @endif
    </div>

    <div class="-mx-4 flex gap-2 overflow-x-auto px-4 [scrollbar-width:none]">
        @foreach ($typeChips as $key => $label)
            @php($on = $activeChip === $key)
            <button type="button" wire:click="pickType('{{ $key }}')" aria-pressed="{{ $on ? 'true' : 'false' }}"
                    class="h-10 shrink-0 cursor-pointer rounded-full border px-4 text-sm font-semibold {{ $on ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-300 bg-white text-slate-700' }}">
                {{ $label }}
            </button>
        @endforeach
    </div>
</section>
