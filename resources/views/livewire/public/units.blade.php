@php
    $client = tenant();
    $typeLabel = fn (string $t): string => __(ucfirst(str_replace('_', ' ', $t)));
    $pill = fn (bool $on): string => 'inline-flex min-h-[40px] cursor-pointer items-center rounded-full border px-4 text-sm font-semibold transition-colors '
        .($on ? 'border-[var(--ink)] bg-[var(--ink)] text-white' : 'border-[#d9d6cf] bg-white text-[var(--ink)] hover:border-[var(--ink)]');
@endphp

<div class="flex flex-col gap-8">
    <header class="flex flex-col gap-2">
        <h1 class="text-3xl font-extrabold leading-tight tracking-tight sm:text-[40px]">{{ __('Rooms at :name', ['name' => $client->name]) }}</h1>
        <p class="text-base text-[var(--muted)] sm:text-[17px]">
            @if ($hasShortStays && $hasLongTerm)
                {{ __('Stay a night, a week, or rent by the month.') }}
            @elseif ($hasShortStays)
                {{ __('Stay a night or a week.') }}
            @else
                {{ __('Rooms and spaces to rent.') }}
            @endif
            @if ($client->whatsappUrl())
                {{ __('Book here or on WhatsApp.') }}
            @endif
        </p>
    </header>

    {{-- Search: how you want to stay, and a name or area. --}}
    <div class="flex max-w-3xl flex-wrap items-stretch overflow-hidden rounded-[20px] border border-[#d9d6cf] bg-white shadow-[0_6px_20px_rgba(29,29,27,0.08)]">
        @if ($hasShortStays && $hasLongTerm)
            <div class="flex items-center gap-1 border-[#ebe9e4] p-2 max-sm:w-full max-sm:border-b sm:border-r">
                @foreach (['' => __('All'), 'night' => __('By the night'), 'month' => __('Monthly')] as $value => $label)
                    <button type="button" wire:click="$set('mode', '{{ $value }}')"
                            class="min-h-[44px] flex-1 rounded-xl px-4 text-sm font-semibold {{ $mode === $value ? 'bg-[var(--ink)] text-white' : 'text-[var(--ink)] hover:bg-[#f4f2ee]' }}">
                        {{ $label }}
                    </button>
                @endforeach
            </div>
        @endif
        <label for="room-search" class="flex min-w-[200px] flex-1 flex-col justify-center gap-0.5 px-5 py-2.5 text-xs font-bold">
            {{ __('Search') }}
            <input id="room-search" wire:model.live.debounce.400ms="search" type="search"
                   placeholder="{{ __('Room, building or area') }}"
                   class="border-0 bg-transparent p-0 text-[15px] font-normal text-[var(--ink)] placeholder:text-[#8a8780] focus:ring-0">
        </label>
    </div>

    {{-- Quick filters --}}
    <div class="flex flex-wrap gap-2">
        @if (count($types) > 1)
            <button type="button" wire:click="$set('type', '')" class="{{ $pill($type === '') }}">{{ __('All types') }}</button>
            @foreach ($types as $t)
                <button type="button" wire:click="$set('type', '{{ $t }}')" class="{{ $pill($type === $t) }}">{{ $typeLabel($t) }}</button>
            @endforeach
        @endif
        <details class="group relative">
            <summary class="{{ $pill($amenities !== [] || $locationId !== '' || $minRent || $maxRent) }} list-none">
                {{ __('More filters') }}@if ($amenities !== []) · {{ count($amenities) }}@endif
            </summary>
            <div class="absolute left-0 z-20 mt-2 grid w-[min(92vw,560px)] gap-4 rounded-2xl border border-[#ebe9e4] bg-white p-5 shadow-[0_12px_32px_rgba(29,29,27,0.14)]">
                <div class="grid gap-3 sm:grid-cols-3">
                    <label class="flex flex-col gap-1 text-xs font-bold">{{ __('Area') }}
                        <select wire:model.live="locationId" class="min-h-[44px] rounded-xl border border-[#d9d6cf] bg-white px-3 text-sm font-normal">
                            <option value="">{{ __('Any area') }}</option>
                            @foreach ($locations as $loc)
                                <option value="{{ $loc->id }}">{{ $loc->district }}, {{ $loc->region }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="flex flex-col gap-1 text-xs font-bold">{{ __('Min monthly rent') }}
                        <input wire:model.live.debounce.400ms="minRent" type="number" inputmode="numeric" placeholder="0" class="min-h-[44px] rounded-xl border border-[#d9d6cf] px-3 text-sm font-normal">
                    </label>
                    <label class="flex flex-col gap-1 text-xs font-bold">{{ __('Max monthly rent') }}
                        <input wire:model.live.debounce.400ms="maxRent" type="number" inputmode="numeric" placeholder="{{ __('Any') }}" class="min-h-[44px] rounded-xl border border-[#d9d6cf] px-3 text-sm font-normal">
                    </label>
                </div>
                <div class="flex flex-wrap gap-2">
                    @foreach ($amenityOptions as $key => $label)
                        <label class="{{ $pill(in_array($key, $amenities, true)) }} min-h-[36px] px-3 text-xs">
                            <input type="checkbox" wire:model.live="amenities" value="{{ $key }}" class="sr-only">
                            {{ $label }}
                        </label>
                    @endforeach
                </div>
            </div>
        </details>
    </div>

    <div id="listings" class="flex items-baseline justify-between gap-4">
        <h2 class="text-xl font-bold">
            @if ($units->total() > 0)
                {{ trans_choice(':count room available|:count rooms available', $units->total()) }}
            @else
                {{ __('No rooms match') }}
            @endif
        </h2>
        <span class="hidden text-sm text-[var(--muted)] sm:inline">{{ __('No booking fees') }}</span>
    </div>

    @if ($units->isEmpty())
        <div class="flex flex-col items-center gap-4 rounded-2xl border border-[#ebe9e4] px-6 py-14 text-center">
            <h3 class="text-xl font-bold">{{ __('Nothing free that matches right now.') }}</h3>
            <p class="max-w-md text-[var(--muted)]">{{ __('Clear the filters, or message us and we will tell you when something opens up.') }}</p>
            <div class="flex flex-wrap justify-center gap-3">
                <button type="button" wire:click="clearFilters"
                        class="inline-flex min-h-[44px] items-center rounded-xl bg-[var(--ink)] px-5 text-sm font-bold text-white">{{ __('Clear filters') }}</button>
                @if ($client->whatsappUrl())
                    <a href="{{ $client->whatsappUrl(__('Hello, do you have a room free?')) }}" target="_blank" rel="noopener"
                       class="inline-flex min-h-[44px] items-center gap-2 rounded-xl border border-[var(--ink)] px-5 text-sm font-bold">
                        <x-cms.whatsapp-icon class="h-5 w-5" /> {{ __('Message us') }}
                    </a>
                @endif
            </div>
        </div>
    @else
        <div class="grid grid-cols-[repeat(auto-fill,minmax(260px,1fr))] gap-x-6 gap-y-8">
            @foreach ($units as $unit)
                <x-cms.room-card :unit="$unit" />
            @endforeach
        </div>

        <div>{{ $units->links() }}</div>
    @endif
</div>
