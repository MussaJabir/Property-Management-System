@props(['unit', 'title'])

{{-- "What this room has": each amenity with its icon. --}}
@if ($unit->amenityLabels() !== [])
    <section class="flex flex-col gap-3.5 border-t border-[var(--line)] pt-7">
        <h2 class="text-[22px] font-bold">{{ $title }}</h2>
        <ul class="grid grid-cols-[repeat(auto-fill,minmax(200px,1fr))] gap-x-6 gap-y-3.5 text-base">
            @foreach ($unit->amenityLabels() as $key => $label)
                <li class="flex items-center gap-3">
                    <svg class="h-[22px] w-[22px] shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">{!! \App\Support\AmenityIcons::path($key) !!}</svg>
                    {{ $label }}
                </li>
            @endforeach
        </ul>
    </section>
@endif
