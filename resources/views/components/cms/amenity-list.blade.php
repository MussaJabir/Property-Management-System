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

    // Curated Heroicon (mini, 20x20) paths per amenity; generic check fallback.
    $icons = [
        'air_conditioning' => '<path d="M10 1a.75.75 0 0 1 .75.75v2.69l1.72-1.72a.75.75 0 1 1 1.06 1.06l-2.78 2.78v2.18l1.89-1.09.71-2.65a.75.75 0 0 1 1.45.39l-.26.97 2.35-1.36a.75.75 0 0 1 .75 1.3l-2.35 1.36.97.26a.75.75 0 1 1-.39 1.45l-2.65-.71L12.28 10l1.89 1.09 2.65-.71a.75.75 0 1 1 .39 1.45l-.97.26 2.35 1.36a.75.75 0 1 1-.75 1.3l-2.35-1.36.26.97a.75.75 0 1 1-1.45.39l-.71-2.65L11.78 11v2.18l2.78 2.78a.75.75 0 1 1-1.06 1.06l-1.72-1.72v2.69a.75.75 0 0 1-1.5 0v-2.69l-1.72 1.72a.75.75 0 0 1-1.06-1.06l2.78-2.78V11l-1.89 1.09-.71 2.65a.75.75 0 1 1-1.45-.39l.26-.97-2.35 1.36a.75.75 0 1 1-.75-1.3l2.35-1.36-.97-.26a.75.75 0 0 1 .39-1.45l2.65.71L8.22 10 6.33 8.91l-2.65.71a.75.75 0 1 1-.39-1.45l.97-.26-2.35-1.36a.75.75 0 1 1 .75-1.3l2.35 1.36-.26-.97a.75.75 0 0 1 1.45-.39l.71 2.65L8.5 8.43V6.25L5.72 3.47a.75.75 0 0 1 1.06-1.06L8.5 4.13V1.75A.75.75 0 0 1 10 1Z" />',
        'wifi' => '<path fill-rule="evenodd" d="M10 16a1 1 0 1 0 0-2 1 1 0 0 0 0 2Zm-3.536-3.464a.75.75 0 0 1 0-1.061 5 5 0 0 1 7.072 0 .75.75 0 1 1-1.06 1.06 3.5 3.5 0 0 0-4.95 0 .75.75 0 0 1-1.062 0ZM4.343 10.4a.75.75 0 0 1-.007-1.06 8 8 0 0 1 11.328 0 .75.75 0 0 1-1.067 1.054 6.5 6.5 0 0 0-9.194 0 .75.75 0 0 1-1.06.006Z" clip-rule="evenodd" />',
        'parking' => '<path fill-rule="evenodd" d="M3 4.25A2.25 2.25 0 0 1 5.25 2h9.5A2.25 2.25 0 0 1 17 4.25v11.5A2.25 2.25 0 0 1 14.75 18h-9.5A2.25 2.25 0 0 1 3 15.75V4.25Zm4 1.5a.75.75 0 0 0-.75.75v7a.75.75 0 0 0 1.5 0v-2h1.75a2.75 2.75 0 1 0 0-5.5H7Zm2.5 4h-1.75V7.25h1.75a1.25 1.25 0 0 1 0 2.5Z" clip-rule="evenodd" />',
        'water_247' => '<path fill-rule="evenodd" d="M10 2a.75.75 0 0 1 .643.365C12.5 5.5 15 8.6 15 11.5a5 5 0 0 1-10 0c0-2.9 2.5-6 4.357-9.135A.75.75 0 0 1 10 2Z" clip-rule="evenodd" />',
        'backup_power' => '<path fill-rule="evenodd" d="M11.983 1.75a.75.75 0 0 0-1.4-.387l-5.5 9.25a.75.75 0 0 0 .644 1.137h3.564l-1.124 5.012a.75.75 0 0 0 1.4.387l5.5-9.25a.75.75 0 0 0-.644-1.137H8.86l1.124-5.012Z" clip-rule="evenodd" />',
        'security' => '<path fill-rule="evenodd" d="M9.661 2.237a.531.531 0 0 1 .678 0 11.947 11.947 0 0 0 7.078 2.749.5.5 0 0 1 .479.425c.069.52.104 1.05.104 1.59 0 5.162-3.26 9.563-7.834 11.256a.48.48 0 0 1-.332 0C5.26 16.564 2 12.163 2 7c0-.538.035-1.069.104-1.589a.5.5 0 0 1 .48-.425 11.947 11.947 0 0 0 7.077-2.75Zm4.196 5.954a.75.75 0 0 0-1.214-.882l-3.236 4.53-1.665-1.664a.75.75 0 0 0-1.06 1.06l2.28 2.28a.75.75 0 0 0 1.135-.089l3.76-5.265Z" clip-rule="evenodd" />',
        'furnished' => '<path d="M3.75 4.5A2.75 2.75 0 0 1 6.5 1.75h7a2.75 2.75 0 0 1 2.75 2.75v.878a2.25 2.25 0 0 1 1.5 2.122v6a.75.75 0 0 1-1.5 0V16h-13v.5a.75.75 0 0 1-1.5 0v-6c0-.98.627-1.815 1.5-2.122V4.5Zm10.75 3.25H5.5a.75.75 0 0 0-.75.75v1h11.5v-1a.75.75 0 0 0-.75-.75h-1Z" />',
        'hot_water' => '<path fill-rule="evenodd" d="M10 2a.75.75 0 0 1 .643.365C12.5 5.5 15 8.6 15 11.5a5 5 0 0 1-10 0c0-2.9 2.5-6 4.357-9.135A.75.75 0 0 1 10 2Z" clip-rule="evenodd" />',
        'fitted_kitchen' => '<path fill-rule="evenodd" d="M3 4.25A2.25 2.25 0 0 1 5.25 2h9.5A2.25 2.25 0 0 1 17 4.25v11.5A2.25 2.25 0 0 1 14.75 18h-9.5A2.25 2.25 0 0 1 3 15.75V4.25Zm2 .75v4h10V5H5Zm0 5.5v4.25c0 .138.112.25.25.25h9.5a.25.25 0 0 0 .25-.25V10.5H5Z" clip-rule="evenodd" />',
        'balcony' => '<path d="M2 9a.75.75 0 0 1 .75-.75h14.5a.75.75 0 0 1 0 1.5H17V17a.75.75 0 0 1-1.5 0v-2h-3v2a.75.75 0 0 1-1.5 0v-2h-2v2a.75.75 0 0 1-1.5 0v-2h-3v2a.75.75 0 0 1-1.5 0V9.75H2.75A.75.75 0 0 1 2 9Z" /><path d="M10 1.75a.75.75 0 0 1 .75.75V7h-1.5V2.5A.75.75 0 0 1 10 1.75Z" />',
        'cctv' => '<path d="M1 4.75A2.75 2.75 0 0 1 3.75 2h8.5l4.74 1.58a1.5 1.5 0 0 1 1.01 1.42v1a1.5 1.5 0 0 1-1.5 1.5h-2.04l-1 3.5a.75.75 0 0 1-.72.55H8.5v2.7a2.3 2.3 0 1 1-1.5 0V11.5H3.75A2.75 2.75 0 0 1 1 8.75v-4Z" />',
        'ensuite' => '<path fill-rule="evenodd" d="M5 2a2 2 0 0 0-2 2v1.5a.75.75 0 0 0 1.5 0V4a.5.5 0 0 1 .5-.5h.5a.75.75 0 0 0 0-1.5H5Zm-2 8.25a.75.75 0 0 1 .75.75 5.5 5.5 0 0 0 11 0 .75.75 0 0 1 1.5 0 7 7 0 0 1-6.25 6.96V19a.75.75 0 0 1-1.5 0v-1.04A7 7 0 0 1 2.25 11a.75.75 0 0 1 .75-.75Z" clip-rule="evenodd" />',
        'garden' => '<path fill-rule="evenodd" d="M10 1c-1.716 0-3.408.106-5.07.31C3.806 1.45 3 2.414 3 3.517V18.25a.75.75 0 0 0 1.075.676L10 16.11l5.925 2.816A.75.75 0 0 0 17 18.25V3.517c0-1.103-.806-2.068-1.93-2.207A41.4 41.4 0 0 0 10 1Z" clip-rule="evenodd" />',
        'servant_quarter' => '<path d="M10 8a3 3 0 1 0 0-6 3 3 0 0 0 0 6ZM3.465 14.493a1.23 1.23 0 0 0 .41 1.412A9.957 9.957 0 0 0 10 18c2.31 0 4.438-.784 6.131-2.1.43-.333.604-.903.408-1.41a7.002 7.002 0 0 0-13.074.003Z" />',
    ];
    $checkPath = '<path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd" />';
@endphp

@if (! empty($labels))
    <div class="flex flex-wrap gap-2">
        @foreach ($shown as $key => $label)
            <span class="inline-flex items-center gap-1.5 rounded-full {{ $compact ? 'px-2.5 py-1 text-[11px]' : 'px-3 py-1.5 text-xs' }} font-medium text-zinc-700 ring-1 ring-zinc-900/[0.08]"
                  style="background-color: color-mix(in srgb, var(--brand) 7%, white);">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"
                     class="{{ $compact ? 'h-3 w-3' : 'h-3.5 w-3.5' }}" style="color: var(--brand);">
                    {!! $icons[$key] ?? $checkPath !!}
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
