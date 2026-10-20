@props(['client', 'size' => 'h-9 w-9'])

{{-- The workspace's logo, or its first letter on the brand colour. --}}
@if ($client?->logoUrl())
    <img src="{{ $client->logoUrl() }}" alt="{{ $client->name }}" {{ $attributes->class([$size, 'shrink-0 rounded-[10px] object-cover']) }}>
@else
    <span {{ $attributes->class([$size, 'inline-flex shrink-0 items-center justify-center rounded-[10px] font-extrabold text-white']) }} style="background-color: var(--brand);">
        {{ mb_substr($client?->name ?? 'S', 0, 1) }}
    </span>
@endif
