@php
    $pill = 'h-10 shrink-0 cursor-pointer rounded-full border px-4 text-sm font-semibold';
    $off = 'border-slate-300 bg-white text-slate-700';
    $on = 'border-slate-900 bg-slate-900 text-white';
    $box = 'flex flex-col gap-3 rounded-[20px] border border-slate-200 bg-white p-4 shadow-[0_2px_10px_rgba(15,23,42,0.05)]';
@endphp

{{-- Phones: every filter in one full-screen sheet. Changes apply live. --}}
<div x-show="sheet" x-cloak x-transition.opacity @keydown.escape.window="sheet = false"
     class="fixed inset-0 z-50 flex flex-col bg-slate-50 sm:hidden" role="dialog" aria-modal="true" aria-label="{{ __('marketplace.search') }}">
    <div class="flex items-center gap-3 px-4 py-3">
        <button type="button" @click="sheet = false" class="inline-flex h-10 w-10 cursor-pointer items-center justify-center rounded-full border border-slate-300 bg-white" aria-label="{{ __('Close') }}">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
        </button>
        <b class="text-base">{{ __('marketplace.search') }}</b>
    </div>

    <div class="flex flex-1 flex-col gap-3 overflow-y-auto px-4 pb-6">
        <div class="{{ $box }}">
            <h2 class="text-2xl font-extrabold">{{ __('marketplace.where_q') }}</h2>
            <label class="relative block">
                <span class="sr-only">{{ __('marketplace.where') }}</span>
                <input type="search" wire:model.live.debounce.400ms="search" placeholder="{{ __('marketplace.where_placeholder') }}"
                       class="h-12 w-full rounded-xl border border-slate-300 bg-white pl-11 text-[15px] focus:border-slate-900 focus:ring-0">
                <svg class="pointer-events-none absolute left-3.5 top-3.5 h-5 w-5 text-slate-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
            </label>
            @if ($areas !== [])
                <span class="text-xs font-bold uppercase tracking-wider text-slate-500">{{ __('marketplace.areas_with_places') }}</span>
                <div class="flex flex-col">
                    @foreach ($areas as $i => $area)
                        @php($picked = trim($search) === $area['region'])
                        <button type="button" wire:click="$set('search', @js($area['region']))" aria-pressed="{{ $picked ? 'true' : 'false' }}"
                                class="flex min-h-16 cursor-pointer items-center gap-3.5 rounded-2xl px-1 text-left {{ $picked ? 'bg-teal-50' : '' }}">
                            <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-[14px] {{ ['bg-sky-100 text-sky-700', 'bg-green-100 text-green-700', 'bg-amber-100 text-amber-700', 'bg-violet-100 text-violet-700'][$i % 4] }}">
                                <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 21s7-6 7-12a7 7 0 0 0-14 0c0 6 7 12 7 12z"/><circle cx="12" cy="9" r="2.5"/></svg>
                            </span>
                            <span class="flex min-w-0 flex-col">
                                <b class="text-[15px]">{{ $area['region'] }}</b>
                                <span class="text-[13px] text-slate-500">
                                    {{ trans_choice('marketplace.count', $area['total']) }}@if ($area['from']) · {{ __('marketplace.from_price', ['amount' => 'TZS '.number_format($area['from']), 'per' => $area['per'] === 'night' ? __('night') : __('month')]) }}@endif
                                </span>
                            </span>
                            @if ($picked)
                                <svg class="ml-auto h-5 w-5 shrink-0 text-teal-700" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12l4 4L19 6"/></svg>
                            @endif
                        </button>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="{{ $box }}">
            <h2 class="text-lg font-extrabold">{{ __('marketplace.how_long') }}</h2>
            <div class="flex flex-wrap gap-2">
                @foreach (['' => __('marketplace.stay_any'), 'night' => __('marketplace.stay_night'), 'month' => __('marketplace.stay_month')] as $value => $label)
                    <button type="button" wire:click="$set('stay', @js($value))" class="{{ $pill }} {{ $stay === $value ? $on : $off }}">{{ $label }}</button>
                @endforeach
            </div>
        </div>

        <div class="{{ $box }}">
            <h2 class="text-lg font-extrabold">{{ __('marketplace.budget') }}</h2>
            <label class="flex flex-col gap-1.5 text-[13px] font-semibold text-slate-700">
                {{ $stay === 'night' ? __('marketplace.max_price_night') : __('marketplace.max_price_month') }}
                <input type="number" min="0" step="5000" inputmode="numeric" wire:model.live.debounce.500ms="max" placeholder="{{ __('marketplace.any') }}"
                       class="h-12 rounded-xl border border-slate-300 bg-white px-3.5 text-[15px] font-normal focus:border-slate-900 focus:ring-0">
            </label>
            <div class="flex flex-wrap gap-2">
                @foreach ($stay === 'night' ? [30000, 60000, 100000] : [200000, 400000, 800000] as $amount)
                    <button type="button" wire:click="$set('max', {{ $amount }})" class="{{ $pill }} {{ (int) $max === $amount ? $on : $off }}">{{ number_format($amount / 1000) }}k</button>
                @endforeach
            </div>
        </div>

        <div class="{{ $box }}">
            <h2 class="text-lg font-extrabold">{{ __('marketplace.bedrooms_extras') }}</h2>
            <div class="flex flex-wrap gap-2">
                @foreach ([null => __('marketplace.any'), 1 => '1+', 2 => '2+', 3 => '3+'] as $value => $label)
                    <button type="button" wire:click="$set('bedrooms', {{ $value === '' ? 'null' : $value }})" class="{{ $pill }} {{ (int) $bedrooms === (int) $value ? $on : $off }}">{{ $label }}</button>
                @endforeach
            </div>
            <div class="flex flex-wrap gap-2">
                @foreach ($amenityOptions as $key => $label)
                    @php($picked = in_array($key, $amenities, true))
                    <button type="button" wire:click="toggleAmenity('{{ $key }}')" aria-pressed="{{ $picked ? 'true' : 'false' }}" class="{{ $pill }} {{ $picked ? $on : $off }}">{{ $label }}</button>
                @endforeach
            </div>
        </div>
    </div>

    <div class="flex items-center justify-between gap-3 border-t border-slate-200 bg-white px-4 pb-[max(16px,env(safe-area-inset-bottom))] pt-3">
        <button type="button" wire:click="clearFilters" class="min-h-11 cursor-pointer text-[15px] font-bold underline">{{ __('marketplace.clear_all') }}</button>
        <button type="button" @click="sheet = false; window.scrollTo({ top: 0 })"
                class="inline-flex min-h-12 cursor-pointer items-center gap-2 rounded-xl bg-teal-700 px-5 text-[15px] font-bold text-white hover:bg-teal-800">
            <svg class="h-[18px] w-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
            <span wire:loading.remove>{{ trans_choice('marketplace.show_count', $units->total()) }}</span>
            <span wire:loading>{{ __('marketplace.searching') }}</span>
        </button>
    </div>
</div>
