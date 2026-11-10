@props(['client', 'size' => 'h-9 w-9'])

@php
    // A wide logo (a name written out) is shown in full at the same height;
    // a square mark keeps its rounded tile.
    $logo = $client?->logoUrl();
    $wide = $logo !== null && $client->logoIsWide();
    $height = collect(explode(' ', $size))->first(fn (string $class): bool => str_starts_with($class, 'h-')) ?? 'h-9';
@endphp

{{-- The workspace's logo, or its first letter on the brand colour. --}}
@if ($wide)
    <img src="{{ $logo }}" alt="{{ $client->name }}" {{ $attributes->class([$height, 'w-auto max-w-[180px] shrink-0 object-contain']) }}>
@elseif ($logo)
    <img src="{{ $logo }}" alt="{{ $client->name }}" {{ $attributes->class([$size, 'shrink-0 rounded-[10px] object-cover']) }}>
@else
    <span {{ $attributes->class([$size, 'inline-flex shrink-0 items-center justify-center rounded-[10px] font-extrabold text-white']) }} style="background-color: var(--brand);">
        {{ mb_substr($client?->name ?? 'S', 0, 1) }}
    </span>
@endif
