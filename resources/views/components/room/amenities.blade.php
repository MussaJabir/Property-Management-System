@props(['unit', 'title', 'limit' => null])

{{-- "What this room has": each amenity with its icon; with a limit, the rest open with "Show all". --}}
@php($labels = $unit->amenityLabels())
@if ($labels !== [])
    <section x-data="{ all: false }" class="flex flex-col gap-3.5 border-t border-[var(--line)] pt-7">
        <h2 class="text-[22px] font-bold">{{ $title }}</h2>
        <ul class="grid grid-cols-[repeat(auto-fill,minmax(200px,1fr))] gap-x-6 gap-y-3.5 text-base">
            @foreach (array_keys($labels) as $i => $key)
                <li class="flex items-center gap-3" @if ($limit && $i >= $limit) x-show="all" x-cloak @endif>
                    <svg class="h-[22px] w-[22px] shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">{!! \App\Support\AmenityIcons::path($key) !!}</svg>
                    {{ $labels[$key] }}
                </li>
            @endforeach
        </ul>
        @if ($limit && count($labels) > $limit)
            <button type="button" x-show="! all" @click="all = true"
                    class="min-h-12 cursor-pointer rounded-xl bg-slate-100 px-4 text-[15px] font-bold md:self-start">
                {{ __('marketplace.show_all_amenities', ['count' => count($labels)]) }}
            </button>
        @endif
    </section>
@endif
