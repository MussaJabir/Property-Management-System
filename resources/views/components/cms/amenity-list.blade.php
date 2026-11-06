@props([
    'unit',
    'limit' => null,   // null = show all; integer = show first N then "+X more"
    'compact' => false, // compact = small pills (used on cards)
])

@php
    $labels = $unit->amenityLabels();
    if (empty($labels)) {
        // Nothing to render; the parent decides whether to show a placeholder.
        $labels = [];
    }

    $total = count($labels);
    $shown = $limit ? array_slice($labels, 0, $limit, true) : $labels;
    $remaining = $limit ? max(0, $total - $limit) : 0;

@endphp

@if (! empty($labels))
    <div class="flex flex-wrap gap-2">
        @foreach ($shown as $key => $label)
            <span class="inline-flex items-center gap-1.5 rounded-full {{ $compact ? 'px-2.5 py-1 text-[11px]' : 'px-3 py-1.5 text-xs' }} font-medium text-zinc-700 ring-1 ring-zinc-900/[0.08]"
                  style="background-color: color-mix(in srgb, var(--brand) 7%, white);">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"
                     class="{{ $compact ? 'h-3 w-3' : 'h-3.5 w-3.5' }}" style="color: var(--brand);">
                    {!! \App\Support\AmenityIcons::path($key) !!}
                </svg>
                {{ $label }}
            </span>
        @endforeach

        @if ($remaining > 0)
            <span class="inline-flex items-center rounded-full {{ $compact ? 'px-2.5 py-1 text-[11px]' : 'px-3 py-1.5 text-xs' }} font-semibold text-zinc-500 ring-1 ring-zinc-900/[0.08]">
                +{{ $remaining }}
            </span>
        @endif
    </div>
@endif
