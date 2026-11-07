{{-- Phones, before any search: one swipeable row per area, busiest first. --}}
<div class="-mx-4 flex flex-col gap-7 sm:hidden" wire:loading.class="opacity-60">
    @foreach ($rows as $row)
        <section class="flex flex-col gap-3" wire:key="row-{{ $row['region'] }}">
            <div class="flex items-center justify-between gap-3 px-4">
                <h2 class="text-xl font-extrabold">
                    {{ $stay === 'night' ? __('marketplace.stays_in', ['area' => $row['region']]) : __('marketplace.places_in', ['area' => $row['region']]) }}
                </h2>
                <button type="button" wire:click="$set('search', @js($row['region']))"
                        class="inline-flex h-10 w-10 shrink-0 cursor-pointer items-center justify-center rounded-full bg-slate-100"
                        aria-label="{{ __('marketplace.see_all_in', ['area' => $row['region']]) }}">
                    <svg class="h-[18px] w-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14"/><path d="m13 6 6 6-6 6"/></svg>
                </button>
            </div>
            <div class="flex snap-x snap-mandatory scroll-px-4 gap-3 overflow-x-auto px-4 pb-1 [scrollbar-width:none]">
                @foreach ($row['units'] as $unit)
                    <x-marketplace.mini-card :unit="$unit" :rating="$ratings[$unit->id] ?? null" :query="$searchQuery" class="w-[42%] shrink-0 snap-start" />
                @endforeach
                @if ($row['total'] > $row['units']->count())
                    <button type="button" wire:click="$set('search', @js($row['region']))"
                            class="flex w-[30%] shrink-0 cursor-pointer snap-start flex-col items-center justify-center gap-2 rounded-2xl bg-slate-50 text-sm font-semibold text-teal-800">
                        <span class="inline-flex h-11 w-11 items-center justify-center rounded-full bg-white shadow-sm">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14"/><path d="m13 6 6 6-6 6"/></svg>
                        </span>
                        {{ __('marketplace.see_all_count', ['count' => $row['total']]) }}
                    </button>
                @endif
            </div>
        </section>
    @endforeach
</div>
