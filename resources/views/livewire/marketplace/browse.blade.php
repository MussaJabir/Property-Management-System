<div>
    <section class="border-b border-slate-100 bg-slate-50">
        <div class="mx-auto flex max-w-7xl flex-col gap-5 px-4 pb-6 pt-7 sm:px-6 sm:pb-8 sm:pt-10">
            <div class="flex flex-col gap-2">
                <h1 class="text-[27px] font-extrabold leading-tight sm:text-4xl">{{ __('marketplace.headline') }}</h1>
                <p class="max-w-2xl text-sm text-slate-600 sm:text-base">{{ __('marketplace.intro') }}</p>
            </div>

            <form wire:submit="$refresh" class="grid grid-cols-2 gap-2 rounded-2xl border border-slate-200 bg-white p-2 shadow-sm lg:grid-cols-[2fr_1fr_1fr_1fr_auto]">
                <label class="col-span-2 flex flex-col gap-0.5 rounded-xl px-3 py-2 lg:col-span-1">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-slate-600">{{ __('marketplace.where') }}</span>
                    <input type="search" wire:model.live.debounce.400ms="search" list="pms-regions"
                           placeholder="{{ __('marketplace.where_placeholder') }}"
                           class="border-0 bg-transparent p-0 text-[15px] text-slate-900 placeholder:text-slate-400 focus:ring-0">
                    <datalist id="pms-regions">
                        @foreach ($regions as $region)
                            <option value="{{ $region }}"></option>
                        @endforeach
                    </datalist>
                </label>
                <label class="flex flex-col gap-0.5 rounded-xl px-3 py-2">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-slate-600">{{ __('marketplace.stay') }}</span>
                    <select wire:model.live="stay" class="border-0 bg-transparent p-0 text-[15px] text-slate-900 focus:ring-0">
                        <option value="">{{ __('marketplace.stay_any') }}</option>
                        <option value="night">{{ __('marketplace.stay_night') }}</option>
                        <option value="month">{{ __('marketplace.stay_month') }}</option>
                    </select>
                </label>
                <label class="flex flex-col gap-0.5 rounded-xl px-3 py-2">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-slate-600">
                        {{ $stay === 'night' ? __('marketplace.max_price_night') : __('marketplace.max_price_month') }}
                    </span>
                    <input type="number" min="0" step="5000" inputmode="numeric" wire:model.live.debounce.500ms="max"
                           placeholder="{{ __('marketplace.any') }}"
                           class="border-0 bg-transparent p-0 text-[15px] text-slate-900 placeholder:text-slate-400 focus:ring-0">
                </label>
                <label class="flex flex-col gap-0.5 rounded-xl px-3 py-2">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-slate-600">{{ __('marketplace.bedrooms') }}</span>
                    <select wire:model.live="bedrooms" class="border-0 bg-transparent p-0 text-[15px] text-slate-900 focus:ring-0">
                        <option value="">{{ __('marketplace.any') }}</option>
                        <option value="1">1+</option>
                        <option value="2">2+</option>
                        <option value="3">3+</option>
                    </select>
                </label>
                <button type="submit" class="col-span-2 inline-flex min-h-12 items-center justify-center gap-2 rounded-xl bg-teal-700 px-6 text-[15px] font-bold text-white hover:bg-teal-800 lg:col-span-1">
                    <svg class="h-[18px] w-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                    {{ __('marketplace.search') }}
                </button>
            </form>

            <div class="-mx-4 flex gap-2 overflow-x-auto px-4 pb-1 sm:mx-0 sm:flex-wrap sm:px-0">
                @foreach ($amenityOptions as $key => $label)
                    @php($on = in_array($key, $amenities, true))
                    <button type="button" wire:click="toggleAmenity('{{ $key }}')" aria-pressed="{{ $on ? 'true' : 'false' }}"
                            class="h-10 shrink-0 rounded-full border px-4 text-[13px] font-semibold {{ $on ? 'border-teal-700 bg-teal-700 text-white' : 'border-slate-300 bg-white text-slate-700 hover:border-slate-400' }}">
                        {{ $label }}
                    </button>
                @endforeach
            </div>
        </div>
    </section>

    <main class="mx-auto flex max-w-7xl flex-col gap-5 px-4 pb-10 pt-6 sm:px-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm text-slate-600" wire:loading.class="opacity-50">
                <strong class="text-slate-900">
                    @if (trim($search) !== '')
                        {{ trans_choice('marketplace.count_in', $units->total(), ['area' => trim($search)]) }}
                    @else
                        {{ trans_choice('marketplace.count', $units->total()) }}
                    @endif
                </strong>
                @if ($filtered)
                    · <button type="button" wire:click="clearFilters" class="cursor-pointer font-semibold text-teal-700 hover:underline">{{ __('marketplace.clear') }}</button>
                @endif
            </p>
            <label class="flex items-center gap-2 text-sm text-slate-600">
                {{ __('marketplace.sort') }}
                <select wire:model.live="sort" class="rounded-lg border-slate-300 py-1.5 text-sm">
                    <option value="featured">{{ __('marketplace.sort_featured') }}</option>
                    <option value="newest">{{ __('marketplace.sort_newest') }}</option>
                    <option value="price">{{ __('marketplace.sort_price') }}</option>
                </select>
            </label>
        </div>

        @if ($units->isEmpty())
            <div class="rounded-2xl border border-dashed border-slate-300 px-6 py-14 text-center">
                <p class="text-lg font-bold">{{ __('marketplace.empty_title') }}</p>
                <p class="mx-auto mt-1 max-w-md text-sm text-slate-600">{{ __('marketplace.empty_body') }}</p>
                @if ($regions !== [])
                    <p class="mt-5 text-xs font-bold uppercase tracking-wider text-slate-500">{{ __('marketplace.popular_areas') }}</p>
                    <div class="mt-2 flex flex-wrap justify-center gap-2">
                        @foreach ($regions as $region)
                            <button type="button" wire:click="$set('search', @js($region))" class="h-10 rounded-full border border-slate-300 px-4 text-sm font-semibold text-slate-700 hover:border-slate-400">{{ $region }}</button>
                        @endforeach
                    </div>
                @endif
            </div>
        @else
            <div class="grid grid-cols-1 gap-x-5 gap-y-8 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4" wire:loading.class="opacity-60">
                @foreach ($units as $unit)
                    <x-marketplace.listing-card :unit="$unit" :rating="$ratings[$unit->id] ?? null" />
                @endforeach
            </div>

            <div>{{ $units->links() }}</div>
        @endif

        <section class="mt-6 flex flex-col gap-4 rounded-3xl bg-slate-900 p-6 text-white sm:flex-row sm:items-center sm:justify-between sm:p-8">
            <div class="max-w-2xl">
                <h2 class="text-xl font-extrabold sm:text-2xl">{{ __('marketplace.cta_title') }}</h2>
                <p class="mt-1.5 text-sm text-slate-300 sm:text-[15px]">{{ __('marketplace.cta_body') }}</p>
            </div>
            <a href="https://wa.me/{{ config('services.bjp.whatsapp') }}?text={{ rawurlencode(__('marketplace.cta_whatsapp_text')) }}" target="_blank" rel="noopener"
               class="inline-flex h-12 shrink-0 items-center justify-center rounded-xl bg-white px-6 text-[15px] font-bold text-slate-900 hover:bg-slate-100">{{ __('marketplace.cta_button') }}</a>
        </section>
    </main>
</div>
