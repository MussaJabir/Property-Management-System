<div class="space-y-6">
    <header class="space-y-1">
        <h1 class="text-3xl font-bold sm:text-4xl">{{ __('Available units') }}</h1>
        <p class="text-base text-zinc-600 dark:text-zinc-300">{{ __('Filter by location, type and budget.') }}</p>
    </header>

    <div class="rounded-2xl bg-white p-4 shadow-sm dark:bg-zinc-900 dark:ring-1 dark:ring-white/10">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-6">
            <input wire:model.live.debounce.400ms="search" type="search" placeholder="{{ __('Search') }}"
                   class="rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950 lg:col-span-2">

            <select wire:model.live="type" class="rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950">
                <option value="">{{ __('Any type') }}</option>
                @foreach ($types as $t)
                    <option value="{{ $t }}">{{ str_replace('_', ' ', ucfirst($t)) }}</option>
                @endforeach
            </select>

            <select wire:model.live="locationId" class="rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950">
                <option value="">{{ __('Any location') }}</option>
                @foreach ($locations as $loc)
                    <option value="{{ $loc->id }}">{{ $loc->region }} · {{ $loc->district }}</option>
                @endforeach
            </select>

            <input wire:model.live.debounce.400ms="minRent" type="number" placeholder="{{ __('Min rent') }}"
                   class="rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950">

            <input wire:model.live.debounce.400ms="maxRent" type="number" placeholder="{{ __('Max rent') }}"
                   class="rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950">
        </div>
    </div>

    @if ($units->isEmpty())
        <div class="rounded-xl bg-white p-12 text-center text-sm text-zinc-500 shadow-sm dark:bg-zinc-900 dark:ring-1 dark:ring-white/10">
            {{ __('No units match those filters. Try widening your search.') }}
        </div>
    @else
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($units as $unit)
                <article class="overflow-hidden rounded-xl bg-white shadow-sm transition hover:shadow-md dark:bg-zinc-900 dark:ring-1 dark:ring-white/10">
                    <div class="aspect-[16/10] w-full bg-zinc-100 dark:bg-zinc-800"></div>
                    <div class="p-4">
                        <p class="text-xs uppercase tracking-wider text-zinc-500">{{ str_replace('_', ' ', ucfirst($unit->type ?? 'unit')) }}</p>
                        <h3 class="mt-1 text-base font-semibold">{{ $unit->code }}</h3>
                        <p class="text-xs text-zinc-500">{{ $unit->property?->name }} · {{ $unit->property?->location?->region }}</p>
                        <div class="mt-3 flex items-baseline justify-between">
                            <p class="text-base font-bold" style="color: var(--brand);">
                                {{ $unit->rent_currency }} {{ number_format($unit->rent_amount / 100, 0, '.', ',') }}
                            </p>
                            <span class="text-xs text-zinc-500">/ {{ str_replace('_', ' ', $unit->billing_cycle) }}</span>
                        </div>
                        @if ($unit->bedrooms || $unit->bathrooms || $unit->size_sqm)
                            <div class="mt-3 flex gap-3 border-t border-zinc-100 pt-3 text-xs text-zinc-500 dark:border-zinc-800">
                                @if ($unit->bedrooms) <span>{{ $unit->bedrooms }} {{ __('bd') }}</span> @endif
                                @if ($unit->bathrooms) <span>{{ $unit->bathrooms }} {{ __('ba') }}</span> @endif
                                @if ($unit->size_sqm) <span>{{ $unit->size_sqm }} m²</span> @endif
                            </div>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>
        <div>{{ $units->links() }}</div>
    @endif
</div>
